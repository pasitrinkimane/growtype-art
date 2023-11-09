<?php

class Retrieve_Model_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        try {
            $crud = new Leonardo_Ai_Crud();
            $crud->retrieve_single_generation($job_payload['model_id'], $job_payload['user_nr'], $job_payload['generation_id']);
        } catch (Exception $e) {
            /**
             * Update available_at time
             */
            $available_at = date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + 90);

            Growtype_Cron_Crud::update_record(Growtype_Cron_Database::JOBS_TABLE, [
                'available_at' => $available_at,
            ], $job['id']);

            throw new Exception($e->getMessage());
        }
    }
}
