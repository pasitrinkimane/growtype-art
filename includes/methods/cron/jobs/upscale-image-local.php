<?php

try {
    $image_path = isset($job_payload['path']) ? $job_payload['path'] : null;

    if (empty($image_path)) {
        return;
    }

    $max_width = isset($job_payload['max_width']) & !empty($job_payload['max_width']) ? $job_payload['max_width'] : 650;

    $size = getimagesize($image_path);

    if ($size[0] < $max_width) {
        shell_exec('cd ' . GROWTYPE_AI_PATH . 'resources/plugins/realesrgan-mac; sh run.sh ' . $image_path . ' ' . $image_path . '  2>&1');

        $resmush = new Resmush();
        $img_url = $resmush->compress($image_path);
    }

    /**
     * Delete job
     */
    Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
} catch (Exception $e) {
    Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
        'reserved' => 0,
        'exception' => $e->getMessage(),
    ], $job['id']);
}

