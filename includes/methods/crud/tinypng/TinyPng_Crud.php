<?php

require GROWTYPE_ART_PATH . '/vendor/autoload.php';

class TinyPng_Crud
{
    public function __construct()
    {
        $this->api_key = get_option('growtype_art_tinypng_api_key');

        \Tinify\setKey($this->api_key);
    }

    public function compress($file)
    {
        $source = \Tinify\fromUrl($file['url']);

        $growtype_art_upload_dir = growtype_art_get_upload_dir();

        $optimized_file_path = $growtype_art_upload_dir . "/" . $file['name'] . "." . $file['extension'];

        $source->toFile($optimized_file_path);

        return $optimized_file_path;
    }
}
