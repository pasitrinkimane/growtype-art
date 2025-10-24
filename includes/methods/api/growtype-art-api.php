<?php

class Growtype_Art_Api
{
    public function __construct()
    {
        $this->load_methods();
    }

    function load_methods()
    {
        /**
         *
         */
        include_once 'partials/Growtype_Art_Api_Model.php';
        new Growtype_Art_Api_Model();

        /**
         *
         */
        include_once 'partials/Growtype_Art_Api_Meal.php';
        new Growtype_Art_Api_Meal();

        /**
         *
         */
        include_once 'partials/Growtype_Art_Api_Character.php';
        new Growtype_Art_Api_Character();

        /**
         *
         */
        include_once 'partials/Growtype_Art_Api_Image.php';
        new Growtype_Art_Api_Image();

        /**
         *
         */
        include_once 'partials/Growtype_Art_Api_Color.php';
        new Growtype_Art_Api_Color();
    }

    public static function fire_webhook($url, $data = [], $credentials = [])
    {
//        error_log(sprintf('Webhook fired: %s', print_r([
//            'url' => $url,
//            'data' => $data,
//            'credentials' => $credentials,
//        ], true)));

        $wp_request_headers = [
            'Authorization' => 'Basic ' . base64_encode(($credentials['user'] ?? '') . ':' . ($credentials['password'] ?? '')),
            'Content-Type' => 'application/json',
        ];

        $body = json_encode($data);

        $args = [
            'body' => $body,
            'timeout' => 40,
            'sslverify' => true,
            'headers' => $wp_request_headers
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            error_log('Webhook Error: ' . $response->get_error_message());
        } else {
            error_log(sprintf('Webhook fired: %s', print_r([
                'url' => $url,
//                '$args' => $args,
            ], true)));
        }
    }
}
