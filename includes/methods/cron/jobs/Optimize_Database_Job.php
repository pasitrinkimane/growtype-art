<?php

class Optimize_Database_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        if ($job_payload['action'] === 'sync-local-images') {
            Growtype_Art_Database_Optimize::sync_local_images();
        }
    }
}


