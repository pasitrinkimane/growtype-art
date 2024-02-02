<?php

class Retrieve_Upscale_Image_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        /**
         * Try to retrieve the image
         */
        $get_url = $job_payload['upscaled_image']['urls']['get'];

        $replicate = new ReplicateCrud();

        $retrieve = $replicate->real_esrgan_retrieve($get_url);

        $output = $retrieve['output'];

        error_log(print_r([
            'action' => 'retrieved',
            'url' => $output
        ], true));

        if (empty($output)) {
            throw new Exception('Not generated yet');
        }

        try {
            $resmush = new Resmush_Crud();

            $img_url = $resmush->compress_online($output);

            if (empty($img_url)) {
                throw new Exception('Image not compressed');
            }

            error_log(print_r([
                'action' => 'compressed',
                'url' => $img_url,
                'public_id' => $job_payload['original_image']['public_id'],
            ], true));

            $cloudinary = new Cloudinary_Crud();

            $public_id = $job_payload['original_image']['public_id'];

            $cloudinary->upload_asset($img_url, [
                'public_id' => $public_id
            ]);

            $cloudinary->add_context($public_id, [
                'real_esrgan' => 'true',
                'compressed' => 'true'
            ]);

            $image_id = $job_payload['original_image']['id'];

            $image = growtype_ai_get_image_details($image_id);

            if (!isset($image['settings']['real_esrgan'])) {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $image_id,
                    'meta_key' => 'real_esrgan',
                    'meta_value' => 'true',
                ]);
            }

            if (!isset($image['settings']['compressed'])) {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $image_id,
                    'meta_key' => 'compressed',
                    'meta_value' => 'true',
                ]);
            }

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
