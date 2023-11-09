<?php

class Generate_Model_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $crud = new Leonardo_Ai_Crud();
        $crud->generate_model($job_payload['model_id']);
    }
}
