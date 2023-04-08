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

        return null;
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

/**
 * Public id
 */
if (!function_exists('growtype_ai_get_images_categories')) {
    function growtype_ai_get_images_categories()
    {
        return array (
            "Abstract" => array (
                "Shapes",
                "Expressionism",
                "Landscapes",
                "Portraits",
                "Liquid"
            ),
            "Animals" => array (
                "Wild",
                "Domestic",
                "Aquatic",
                "Dogs",
                "Cats"
            ),
            "Architecture" => array (
                "Buildings",
                "Cityscapes",
                "Bridges",
                "Skylines"
            ),
            "Black and White" => array (
                "Landscapes",
                "Portraits",
                "Cityscapes",
                "Abstract"
            ),
            "Botanical" => array (
                "Flowers",
                "Leaves",
                "Trees",
                "Cacti"
            ),
            "Blueprints" => array (
                "Architectural",
                "Engineering",
                "Mechanical",
                "Product Design"
            ),
            "Cars" => array (
                "Vintage",
                "Sports",
                "Luxury",
                "Concept"
            ),
            "Cartoons" => array (
                "Classic Cartoons",
                "Modern Cartoons",
                "Anime",
                "Animated Movies"
            ),
            "Cityscapes" => array (
                "New York City",
                "Paris",
                "Tokyo",
                "Venice"
            ),
            "Comics" => array (
                "Superheroes",
                "Manga",
                "Graphic Novels",
                "Comic Strips"
            ),
            "Contemporary" => array (
                "Abstract",
                "Landscapes",
                "Portraits",
                "Cityscapes"
            ),
            "Fantasy" => array (
                "Dragons",
                "Unicorns",
                "Mythical Creatures",
                "Fantasy Landscapes"
            ),
            "Floral" => array (
                "Patterns",
                "Portraits",
                "Still Life",
                "Abstract"
            ),
            "Food and drinks" => [
                "Coffee",
                "Wine",
                "Beer",
                "Cocktails",
            ],
            "Funny" => [
                "Puns",
                "Sarcasm",
                "Satire"
            ],
            "Fashion" => [
                "Shoes",
                "Accessories",
                "Haute Couture"
            ],
            "Gaming" => [
                "Retro",
                "Fantasy",
                "FPS"
            ],
            "Graphical" => [
                "Infographics",
                "Charts and Graphs",
                "Data Visualizations"
            ],
            "Inspirational" => [
                "Motivational Quotes",
                "Religious",
                "Self-Help"
            ],
            "Illustrations" => [
                "People",
                "Nature",
                "Graphic",
                "Food",
                "Animals"
            ],
            "Anime & Manga" => [
                "Shonen",
                "Shojo",
                "Mecha"
            ],
            "Landscapes" => [
                "Mountains",
                "Beaches",
                "Deserts",
                "Fantasy"
            ],
            "Maps" => [
                "Antique Maps",
                "City Maps",
                "Topographical Maps"
            ],
            "Military" => [
                "Warplanes",
                "Tanks",
                "Naval"
            ],
            "Minimalistic" => [
                "Abstract",
                "Typography",
                "Patterns"
            ],
            "Movies" => [
                "Classic Films",
                "Blockbusters",
                "Foreign Films"
            ],
            "Music" => [
                "Rock",
                "Jazz",
                "Classical"
            ],
            "Nature" => [
                "Flowers",
                "Trees",
                "Wildlife"
            ],
            "Paintings" => [
                "Impressionism",
                "Surrealism",
                "Realism"
            ],
            "People" => [
                "Celebrity",
                "Historical Figures",
                "Fantasy"
            ],
            "Art Movements" => [
                "Renaissance",
                "Baroque",
                "Rococo",
                "Neoclassicism",
                "Romanticism",
                "Realism",
                "Impressionism",
                "Post-Impressionism",
                "Expressionism",
                "Cubism",
                "Futurism",
                "Dadaism",
                "Surrealism",
                "Pop Art"
            ],
            "Photography" => [
                "Black and White",
                "Color",
                "Landscape",
                "Portraits"
            ],
            "Space" => [
                "Planets",
                "Astronomy",
                "Spacecraft"
            ],
            "Sports" => [
                "Football",
                "Basketball",
                "Baseball"
            ],
            "Tv Shows" => [
                "Drama",
                "Comedy",
                "Science Fiction"
            ],
            "Travel" => [
                "Cities",
                "Landmarks",
                "Beaches"
            ],
            "Vintage" => [
                "Victorian Era",
                "Art Deco",
                "Mid-Century Modern"
            ]
        );
    }
}

function color_code_to_group($color_code)
{
    list($r, $g, $b) = sscanf($color_code, "#%02x%02x%02x");

//    ddd([$r, $g, $b]);

    $colors = array (
        "black" => array ("r" => array (0, 30), "g" => array (0, 40), "b" => array (0, 60)),
        "white" => array ("r" => array (190, 255), "g" => array (190, 255), "b" => array (190, 255)),
        "red" => array ("r" => array (150, 255), "g" => array (0, 50), "b" => array (0, 100)),
        "gray" => array ("r" => array (128, 192), "g" => array (128, 192), "b" => array (128, 192)),
        "purple" => array ("r" => array (150, 255), "g" => array (0, 50), "b" => array (100, 255)),
        "green" => array ("r" => array (0, 50), "g" => array (50, 255), "b" => array (0, 50)),
        "blue" => array ("r" => array (0, 60), "g" => array (0, 150), "b" => array (200, 255)),
        "yellow" => array ("r" => array (200, 255), "g" => array (160, 255), "b" => array (0, 150)),
        "orange" => array ("r" => array (240, 255), "g" => array (130, 255), "b" => array (0, 30)),
        "brown" => array ("r" => array (60, 255), "g" => array (30, 50), "b" => array (30, 60))
    );

    foreach ($colors as $color => $ranges) {
        if ($r >= $ranges["r"][0] && $r <= $ranges["r"][1] &&
            $g >= $ranges["g"][0] && $g <= $ranges["g"][1] &&
            $b >= $ranges["b"][0] && $b <= $ranges["b"][1]) {
            return $color;
        }
    }

    return null;
}
