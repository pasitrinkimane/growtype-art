<?php

class Generate_Model_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        if (empty($job_payload)) {
            throw new Exception('Invalid payload');
        }

        if (!isset($job_payload['provider']) || empty($job_payload['provider'])) {
            throw new Exception('Provider is required');
        }

        $provider_class_name = sprintf('\partials\%s_Base', ucfirst($job_payload['provider']));

        if (class_exists($provider_class_name)) {
            $crud = new $provider_class_name();
            $generate_details = $crud->generate_model_image($job_payload['model_id']);
        }

        if (empty($generate_details) || !$generate_details['success']) {
            throw new Exception($generate_details['message'] ?? 'Model generating failed');
        }
    }
}
