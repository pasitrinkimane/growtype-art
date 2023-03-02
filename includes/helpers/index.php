<?php

if (!function_exists('d')) {
    function d($data)
    {
        highlight_string("<?php\n" . var_export($data, true) . ";\n?>");
        die();
    }
}

if (!function_exists('ddd')) {
    function ddd($arr)
    {
        return '<pre>' . var_export($arr, false) . '</pre>';
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

                if (!empty($upload_dir['basedir'])) {
                    $growtype_ai_upload_dir = !empty($folder_name) ? $upload_dir['basedir'] . '/growtype-ai-uploads' . '/' . $folder_name : $upload_dir['basedir'] . '/growtype-ai-uploads';

                    if (!file_exists($growtype_ai_upload_dir)) {
                        wp_mkdir_p($growtype_ai_upload_dir);
                    }

                    // If the function it's not available, require it.
                    if (!function_exists('download_url')) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                    }

                    $tmp_file = download_url($file['url']);

                    $filepath = $growtype_ai_upload_dir . '/' . $file['name'] . '.' . $file['extension'];

                    copy($tmp_file, $filepath);

                    @unlink($tmp_file);
                }

                break;
            case 'cloudinary':
                require_once GROWTYPE_AI_PATH . 'includes/methods/crud/cloudinary/Cloudinary_Crud.php';
                $cloudinary_crud = new Cloudinary_Crud();

                return $cloudinary_crud->upload_image($file, $folder_name);
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
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/cloudinary/Cloudinary_Crud.php';
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
        return Growtype_Ai_Database::get_pivot_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, Growtype_Ai_Database::IMAGES_TABLE, 'image_id', [
                [
                    'key' => 'model_id',
                    'values' => [$model_id],
                ]
            ]
        );
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_details')) {
    function growtype_ai_get_model_details($model_id)
    {
        $model = Growtype_Ai_Database::get_single_record(Growtype_Ai_Database::MODELS_TABLE, [
            [
                'key' => 'id',
                'values' => [$model_id],
            ]
        ]);

        $models_settings = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
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
if (!function_exists('growtype_ai_get_image_model_details')) {
    function growtype_ai_get_image_model_details($image_id)
    {
        $image_model = Growtype_Ai_Database::get_single_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
            [
                'key' => 'image_id',
                'values' => [$image_id],
            ]
        ]);

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

/**
 * Model details
 */
if (!function_exists('growtype_ai_init_generate_image_job')) {
    function growtype_ai_init_generate_image_job($payload)
    {
        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            'queue' => 'generate-model',
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => wp_date('Y-m-d H:i:s'),
            'available_at' => date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + 5),
            'reserved' => 0
        ]);
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_init_retrieve_image_job')) {
    function growtype_ai_init_retrieve_image_job($payload)
    {
        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            'queue' => 'retrieve-model',
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => wp_date('Y-m-d H:i:s'),
            'available_at' => date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + 30),
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

        $model_image = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model_id],
            ]
        ]);

        Growtype_Ai_Database::delete_records(Growtype_Ai_Database::IMAGES_TABLE, array_pluck($model_images, 'id'));
        Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, array_pluck($model_image, 'id'));
    }
}
