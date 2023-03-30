<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

class Resmush
{
    public function __construct()
    {
        define('RESMUSH_WEBSERVICE', 'http://api.resmush.it/ws.php?img=');
    }

    public function compress($path)
    {
        shell_exec('cd ' . GROWTYPE_AI_PATH . '/resources/plugins/resmushit; sh run.sh ' . $path . '  2>&1');
    }

    public function compress_online($url)
    {
        $o = json_decode(file_get_contents(RESMUSH_WEBSERVICE . $url));

        if (isset($o->error)) {
            die('Error');
        }

        return $o->dest;
    }
}
