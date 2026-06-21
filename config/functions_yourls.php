<?php
// /config/functions_yourls.php

function generate_short_url($long_url) {
    $api_url = getenv('YOURLS_API_URL');
    $signature = getenv('YOURLS_SIGNATURE');

    if (empty($api_url) || empty($signature)) {
        error_log("YOURLS Configuration missing. API: $api_url");
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'url'       => $long_url,
        'format'    => 'json',
        'action'    => 'shorturl',
        'signature' => $signature
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $data = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("YOURLS Error: " . $error);
        return false;
    }

    $json = json_decode($data, true);
    if (isset($json['shorturl'])) {
        return $json['shorturl'];
    }

    error_log("YOURLS Invalid Response: " . $data);
    return false;
}
