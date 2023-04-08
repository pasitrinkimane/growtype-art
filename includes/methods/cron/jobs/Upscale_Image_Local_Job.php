<?php

class Upscale_Image_Local_Job
{
    public function run($job_payload)
    {
        $image_path = isset($job_payload['path']) ? $job_payload['path'] : null;

        if (empty($image_path)) {
            return;
        }

        $max_width = isset($job_payload['max_width']) & !empty($job_payload['max_width']) ? $job_payload['max_width'] : 650;

        $size = getimagesize($image_path);

        $upscale_size = 2;

        if ($size[0] < $max_width) {
            shell_exec('cd ' . GROWTYPE_AI_PATH . 'resources/plugins/waifu2x; sh run.sh ' . $image_path . ' ' . $image_path . ' ' . $upscale_size . '  2>&1');

            $resmush = new Resmush();

            $resmush->compress($image_path);
        }
    }
}
