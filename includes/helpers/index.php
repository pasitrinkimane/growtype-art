<?php

if (!function_exists('d')) {
    function d($data)
    {
        highlight_string("<?php\n" . var_export($data, true) . ";\n?>");
        die();
    }
}

if (!function_exists('ddd')) {
    function ddd($data)
    {
        return highlight_string("<?php\n" . var_export($data, true) . ";\n?>");
    }
}

/**
 * Include custom view
 */
if (!function_exists('growtype_ai_include_view')) {
    function growtype_ai_include_view($file_path, $variables = array ())
    {
        $fallback_view = GROWTYPE_AI_PATH . 'resources/views/' . str_replace('.', '/', $file_path) . '.php';
        $child_blade_view = get_stylesheet_directory() . '/views/' . GROWTYPE_AI_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.blade.php';
        $child_view = get_stylesheet_directory() . '/views/' . GROWTYPE_AI_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.php';

        $template_path = $fallback_view;

        if (file_exists($child_blade_view) && function_exists('App\template')) {
            return App\template($child_blade_view, $variables);
        } elseif (file_exists($child_view)) {
            $template_path = $child_view;
        }

        if (file_exists($template_path)) {
            extract($variables);
            ob_start();
            include $template_path;
            $output = ob_get_clean();
        }

        return isset($output) ? $output : '';
    }
}

/**
 * mainly for ajax translations
 */
if (!function_exists('growtype_ai_load_textdomain')) {
    function growtype_ai_load_textdomain($lang)
    {
        global $q_config;

        if (isset($q_config['locale'][$lang])) {
            load_textdomain('growtype-ai', GROWTYPE_AI_PATH . 'languages/growtype-ai-' . $q_config['locale'][$lang] . '.mo');
        }
    }
}

/**
 * Save image
 */
if (!function_exists('growtype_ai_save_file')) {
    function growtype_ai_save_file($file, $folder_name = null)
    {
        $saving_location = $file['location'];

        switch ($saving_location) {
            case 'locally':
                $upload_dir = wp_upload_dir();

                $growtype_ai_upload_dir = growtype_ai_get_upload_dir($folder_name);

                if (!file_exists($growtype_ai_upload_dir)) {
                    wp_mkdir_p($growtype_ai_upload_dir);
                }

                $url = $file['url'];

                $save_file_loc = $growtype_ai_upload_dir . '/' . $file['name'] . '.' . $file['extension'];

                if (file_exists($growtype_ai_upload_dir)) {
                    // Initialize the cURL session
                    $ch = curl_init($url);

                    // Open file
                    $fp = fopen($save_file_loc, 'wb');

                    // It set an option for a cURL transfer
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);

                    // Perform a cURL session
                    curl_exec($ch);

                    // Closes a cURL session and frees all resources
                    curl_close($ch);

                    // Close file
                    fclose($fp);
                }

                break;
            case 'cloudinary':
                try {
                    $cloudinary_crud = new Cloudinary_Crud();
                    $image = $cloudinary_crud->upload_asset($file['url'], [
                        'public_id' => $file['name'],
                        'folder' => $folder_name
                    ]);
                } catch (Exception $e) {
                    throw new Exception($e);
                }

                return $image;
                break;
        }
    }
}

/**
 * Save image
 */
if (!function_exists('growtype_ai_get_external_folder_url')) {
    function growtype_ai_get_external_folder_url($folder_name)
    {
        $cloudinary_crud = new Cloudinary_Crud();
        return $cloudinary_crud->get_folder_url($folder_name);
    }
}

/**
 * Save image
 */
if (!function_exists('growtype_ai_get_model_images')) {
    function growtype_ai_get_model_images($model_id)
    {
        $images = Growtype_Ai_Database_Crud::get_pivot_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, Growtype_Ai_Database::IMAGES_TABLE, 'image_id', [
                [
                    'key' => 'model_id',
                    'values' => [$model_id],
                ]
            ]
        );

        $images_formatted = [];
        foreach ($images as $key => $image) {
            array_push($images_formatted, growtype_ai_get_image_details($image['id']));
        }

        return $images_formatted;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_details')) {
    function growtype_ai_get_model_details($model_id)
    {
        if (empty($model_id)) {
            return null;
        }

        $model = Growtype_Ai_Database_Crud::get_single_record(Growtype_Ai_Database::MODELS_TABLE, [
            [
                'key' => 'id',
                'values' => [$model_id],
            ]
        ]);

        if (empty($model)) {
            return null;
        }

        $models_settings = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model['id']],
            ]
        ]);

        $models_settings_formatted = [];

        foreach ($models_settings as $setting) {
            $models_settings_formatted[$setting['meta_key']] = $setting['meta_value'];
        }

        $model['settings'] = $models_settings_formatted;

        return $model;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_image_details')) {
    function growtype_ai_get_image_details($image_id)
    {
        $image = Growtype_Ai_Database_Crud::get_single_record(Growtype_Ai_Database::IMAGES_TABLE, [
            [
                'key' => 'id',
                'values' => [$image_id],
            ]
        ]);

        $models_settings = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
            [
                'key' => 'image_id',
                'values' => [$image['id']],
            ]
        ]);

        $settings_formatted = [];

        foreach ($models_settings as $setting) {
            $settings_formatted[$setting['meta_key']] = $setting['meta_value'];
        }

        $image['settings'] = $settings_formatted;

        return $image;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_image_model_details')) {
    function growtype_ai_get_image_model_details($image_id)
    {
        $image_model = Growtype_Ai_Database_Crud::get_single_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
            [
                'key' => 'image_id',
                'values' => [$image_id],
            ]
        ]);

        if (empty($image_model)) {
            return null;
        }

        return growtype_ai_get_model_details($image_model['model_id']);
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_generate_reference_id')) {
    function growtype_ai_generate_reference_id()
    {
        return md5(uniqid() . uniqid());
    }
}

if (!function_exists('growtype_ai_init_job')) {
    function growtype_ai_init_job($job_name, $payload, $delay = 5)
    {
        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            'queue' => $job_name,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => wp_date('Y-m-d H:i:s'),
            'available_at' => date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + $delay),
            'reserved' => 0
        ]);
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_delete_model_images')) {
    function growtype_ai_delete_model_images($model_id)
    {
        $model_images = growtype_ai_get_model_images($model_id);

        $model_image = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model_id],
            ]
        ]);

        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::IMAGES_TABLE, array_pluck($model_images, 'id'));
        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, array_pluck($model_image, 'id'));
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_single_setting')) {
    function growtype_ai_get_model_single_setting($model_id, $setting_key)
    {
        $settings = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model_id],
            ]
        ]);

        foreach ($settings as $setting) {
            if ($setting['meta_key'] == $setting_key) {
                return $setting;
            }
        }
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_upload_dir')) {
    function growtype_ai_get_upload_dir($folder_name = null)
    {
        $upload_dir = wp_upload_dir();

        return !empty($folder_name) ? $upload_dir['basedir'] . '/growtype-ai-uploads' . '/' . $folder_name : $upload_dir['basedir'] . '/growtype-ai-uploads';
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_upload_dir_public')) {
    function growtype_ai_get_upload_dir_public($folder_name = null)
    {
        $upload_dir = wp_upload_dir();
        $upload_dir = $upload_dir['baseurl'];

        return !empty($folder_name) ? $upload_dir . '/growtype-ai-uploads' . '/' . $folder_name : $upload_dir . '/growtype-ai-uploads';
    }
}

/**
 * Image
 */
if (!function_exists('growtype_ai_get_image_url')) {
    function growtype_ai_get_image_url($image_id)
    {
        $image = growtype_ai_get_image_details($image_id);

        $location = isset($image['location']) && !empty($image['location']) ? $image['location'] : 'locally';

        $img_url = '';

        if ($location === 'locally') {
            $img_url = growtype_ai_get_upload_dir_public() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
        } elseif ($location === 'cloudinary') {
            $img_url = 'https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
        }

        return $img_url;
    }
}

/**
 * Image
 */
if (!function_exists('growtype_ai_get_image_path')) {
    function growtype_ai_get_image_path($image_id)
    {
        $image = growtype_ai_get_image_details($image_id);

        return growtype_ai_get_upload_dir() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
    }
}

/**
 * Public id
 */
if (!function_exists('growtype_ai_get_cloudinary_public_id')) {
    function growtype_ai_get_cloudinary_public_id($image)
    {
        return $image['folder'] . '/' . $image['name'];
    }
}

/**
 * Public id
 */
if (!function_exists('growtype_ai_get_images_saving_location')) {
    function growtype_ai_get_images_saving_location()
    {
        return get_option('growtype_ai_images_saving_location', 'locally');
    }
}
