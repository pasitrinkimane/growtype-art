<?php

require GROWTYPE_ART_PATH . '/vendor/autoload.php';

class Resmush_Crud
{
    public function compress($path)
    {
        shell_exec('cd ' . GROWTYPE_ART_PATH . '/resources/plugins/resmushit; sh run.sh ' . $path . '  2>&1');
    }

    public function compress_online($img_url) {
        $api_url = "http://api.resmush.it/ws.php?img=" . urlencode($img_url);

        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: GrowtypeBot/1.0\r\n" .
                    "Referer: https://content.nsfwspace.com\r\n"
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents($api_url, false, $context);

        $data = json_decode($response);
        return isset($data->dest) ? $data->dest : '';
    }
}
