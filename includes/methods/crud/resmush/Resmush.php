<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

class Resmush
{
    public function __construct()
    {
        define('RESMUSH_WEBSERVICE', 'http://api.resmush.it/ws.php?img=');
    }

    public function compress($url)
    {
        $o = json_decode(file_get_contents(RESMUSH_WEBSERVICE . $url));

        if (isset($o->error)) {
            die('Error');
        }

        return $o->dest;
    }
}
