<?php
/**
 * Baidu SDK
 * Url: https://cloud.baidu.com/product/ocr/general
 */

return [

    // Doc: https://aip.baidubce.com/oauth/2.0/token
    'token' => [
        'url' => 'https://aip.baidubce.com/oauth/2.0/token',
        'grant_type' => 'client_credentials',
        'client_id' => env('BAIDU_TOKEN_API_KEY', ''),              // API Key
        'client_secret' => env('BAIDU_TOKEN_SECRET_KEY', ''),  // Secret Key
    ],

    'api' => [
        'accurate_basic' => 'https://aip.baidubce.com/rest/2.0/ocr/v1/accurate_basic'
    ]
];

