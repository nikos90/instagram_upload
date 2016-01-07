<?php

require_once 'functions.php';
require_once 'instaW.php';

$username = 'username';
$password = 'password';
$filename = __DIR__.'/400x400.jpg';
$caption = "My author is #crazy ";

$client = new instaW();
$login = $client->login($username,$password);
if($login){
    $media_id = $client->upload_image($filename);
    if($media_id){
        $manipulate = $client->configure_image($media_id,$caption);
        if($manipulate){
            echo 'Your image has been uploaded. Image id: '.$manipulate->id;
            exit;
        }
    }
}

die_pre($client->printError());

