<?php

/**
 * Class instaW
 * Upload to instagram without phone
 * Author: nikos90
 * Repo: https://github.com/nikos90/instagram_upload
 * Credits: http://lancenewman.me/posting-a-photo-to-instagram-without-a-phone/
 */

class instaW {

    private $endpoint = 'https://i.instagram.com/api/v1/';
    private $cookies = 'cookies.txt';
    private $secret = "25eace5393646842f0d0c3fb2ac7d3cfa15c052436ee86b5406a8433f54d24a5"; //DO NOT CHANGE THIS
    private $agent = 'Instagram 6.21.2 Android (19/4.4.2; 480dpi; 1152x1920; Meizu; MX4; mx4; mt6595; en_US)';
    public $debug;
    private $guid;
    private $device_id;

    public function __construct(){
        // define the guid
        $this->guid = $this->generateGuid();
        // set the devide id
        $this->device_id = "android-".$this->guid;
    }

    /**
     * Request interface
     * @param $url
     * @param $post
     * @param $post_data
     * @param $user_agent
     * @param $cookies
     * @return array
     */
    public function sendRequest($url, $post, $post_data, $user_agent, $cookies) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint.$url);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        if($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        if($cookies) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        } else {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        }
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array($http, $response);
    }

    /**
     * Generates the guid for the requests
     * @return string
     */
    public function generateGuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535));
    }

    /**
     * Generate sha256 signature for validating the request
     * @param $data
     * @return string
     */
    public function generateSignature($data) {
        return hash_hmac('sha256', $data, $this->secret);
    }

    /**
     * Prepare the image for posting
     * @param $filename
     * @return array
     */
    public function prepareImageData($filename) {
        if(!is_file($filename)) {
            $this->log("The image doesn't exist ".$filename);
        } else {
            $file = curl_file_create($filename);
            $post_data = array(
                'device_timestamp' => time(),
                'photo' => $file
            );
            return $post_data;
        }
    }

    /**
     * Login to the account
     * @param $username
     * @param $password
     */
    public function login($username,$password){
        $data = '{"device_id":"'.$this->device_id.'","guid":"'.$this->guid.'","username":"'.$username.'","password":"'.$password.'","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}';
        $sig = $this->generateSignature($data,$this->secret);
        $data = 'signed_body='.$sig.'.'.urlencode($data).'&ig_sig_key_version=6';
        $login =  $this->sendRequest('accounts/login/', true, $data, $this->agent, false);
        if(is_array($login) && count($login)==2 && $login[0] == 200 && is_object(json_decode($login[1]))){
            $account = json_decode($login[1]);
            return $account->logged_in_user;
        }else{
            $error = json_decode($login[1]);
            $this->log($error);
        }
    }

    /**
     * Upload photo to instagram
     * @param $filename
     */
    public function upload_image($filename){
        // post the picture
        $data = $this->prepareImageData($filename);
        if($data) {
            $post = $this->sendRequest('media/upload/', true, $data, $this->agent, true);
            if (is_array($post) && count($post) == 2 && $post[0] == 200 && is_object(json_decode($post[1]))) {
                $media = json_decode($post[1]);
                return $media->media_id;
            } else {
                $error = json_decode($post[1]);
                $this->log($error);
            }
        }
    }

    /**
     * Configure and save image
     * @param $media_id
     * @param $caption
     */
    public function configure_image($media_id,$caption){
        $caption = preg_replace("/\r|\n/", "", $caption);
        $data = '{"device_id":"'.$this->device_id.'","guid":"'.$this->guid.'","media_id":"'.$media_id.'","caption":"'.trim($caption).'","device_timestamp":"'.time().'","source_type":"5","filter_type":"0","extra":"{}","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}';
        $sig = $this->generateSignature($data,$this->secret);
        $new_data = 'signed_body='.$sig.'.'.urlencode($data).'&ig_sig_key_version=6';
        // configure the photo
        $conf = $this->sendRequest('media/configure/', true, $new_data, $this->agent, true);
        if(is_array($conf) && count($conf)==2 && $conf[0] == 200 && is_object(json_decode($conf[1]))){
            $response = json_decode($conf[1]);
            if($response->status == 'ok' && isset($response->media)){
                return $response->media;
            }else{
                $this->log($response);
            }
        }else{
            $response = json_decode($conf[1]);
            $this->log($response);
        }
    }

    /**
     * Log the error
     * @param $error
     */
    protected function log($error){
        $this->debug = $error;
    }

    /**
     * Retrieve the error
     * @return mixed
     */
    public function printError(){
        return $this->debug;
    }
}