<?php

class Replicate
{

    public function __construct()
    {
        $this->api_key = get_option('growtype_ai_replicate_api_key');
    }

    public function upscale($upscale_img_url, $original_image)
    {
        $response = $this->real_esrgan_generate($upscale_img_url);

        growtype_ai_init_job('retrieve-upscale-image', json_encode([
            'upscaled_image' => $response,
            'original_image' => $original_image
        ]), 10);
    }

    public function real_esrgan_generate($img_url, $scale = 1.2)
    {
        $url = 'https://api.replicate.com/v1/predictions';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Token ' . $this->api_key,
            ),
            'body' => '{
  "version": "42fed1c4974146d4d2414e2be2c5277c7fcf05fcc3a73abf41610695738c1d7b",
  "input": {
    "image": "' . $img_url . '",
    "scale": "' . $scale . '",
    "face_enhance": "false"
  }
}',
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    public function real_esrgan_retrieve($url)
    {
        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Token ' . $this->api_key,
            ),
            'method' => 'GET'
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }
}

