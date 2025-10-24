<?php

class Compress_Images_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $images_ids = isset($job_payload['images_ids']) ? $job_payload['images_ids'] : null;

        if (empty($images_ids)) {
            return;
        }

        foreach ($images_ids as $images_id) {
            growtype_art_compress_existing_image($images_id);

            sleep(2);
        }
    }
}
