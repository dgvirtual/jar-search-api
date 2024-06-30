<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');


function emailAdmin($subject, $message)
{
    $headers = "From: " . FROM_EMAIL;
    mail(ADMIN_EMAIL, $subject, $message, $headers);
}

function base_url()
{
    return BASE_URL;
   
    //  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
     
    //  // Get the host (domain name)
    //  $host = $_SERVER['HTTP_HOST'];
     
    //  if (substr($host, 0, 5) === 'local' || SUBDIR === '') {
    //     $path = '/';
    // } else {
    //     $path = '/' . SUBDIR . '/';
    // }

    // // Construct the base URL
    // return $protocol . '://' . $host . $path;

}
