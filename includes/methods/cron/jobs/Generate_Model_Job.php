<?php

use partials\Leonardo_Ai_Base;

class Generate_Model_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $crud = new Leonardo_Ai_Base();
        $crud->generate_model($job_payload['model_id']);
    }
}
