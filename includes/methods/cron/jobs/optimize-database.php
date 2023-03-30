<?php

if ($job_payload['action'] === 'sync-local-images') {
    try {
        Growtype_Ai_Database_Optimize::sync_local_images();

        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
    } catch (Exception $e) {
        Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            'exception' => $e->getMessage(),
            'reserved' => 0
        ], $job['id']);
    }

    exit();
}


