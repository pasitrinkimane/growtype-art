<?php

class Retrieve_Model_Job
{
    public function run($job_payload)
    {
        $crud = new Leonardo_Ai_Crud();
        $crud->retrieve_single_generation($job_payload['model_id'], $job_payload['user_nr'], $job_payload['generation_id']);
    }
}
