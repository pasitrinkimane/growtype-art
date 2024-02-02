<?php

include_once 'partials/model.php';
include_once 'partials/character.php';

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
if (!function_exists('growtype_ai_save_external_file')) {
    function growtype_ai_save_external_file($file, $folder_name = null)
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

        if (empty($image)) {
            return null;
        }

        $model_details = growtype_ai_get_image_model_details($image['id']);

        if (empty($model_details)) {
            return null;
        }

        $image['model_id'] = growtype_ai_get_image_model_details($image['id'])['id'];

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
        growtype_cron_init_job($job_name, $payload, $delay);
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
 * Model setting
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

        if (empty($image)) {
            return '';
        }

        $location = isset($image['location']) && !empty($image['location']) ? $image['location'] : 'locally';

        $img_url = '';

        if ($location === 'locally') {
            $img_url = growtype_ai_build_local_image_url($image);
        } elseif ($location === 'cloudinary') {
            $img_url = 'https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
        }

        return $img_url;
    }
}

if (!function_exists('growtype_ai_build_local_image_url')) {
    function growtype_ai_build_local_image_url($image)
    {
        return growtype_ai_get_upload_dir_public() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
    }
}

/**
 * Image
 */
if (!function_exists('growtype_ai_get_image_path')) {
    function growtype_ai_get_image_path($image_id)
    {
        $image = growtype_ai_get_image_details($image_id);

        if (empty($image)) {
            return null;
        }

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
if (!function_exists('growtype_ai_get_art_categories')) {
    function growtype_ai_get_art_categories()
    {
        return array (
            "Abstract" => array (
                "Shapes",
                "Expressionism",
                "Landscapes",
                "Portraits",
                "Liquid",
                "Vibrant",
                "Material",
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
            "Interior" => array (
                "Home",
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
            "Collage" => array (
                "Abstract"
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
                "Animals",
                "Fruits",
                "Vegetable"
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
            ],
            "Style" => [
                "Watercolor",
            ]
        );
    }
}

if (!function_exists('growtype_ai_colors_groups')) {
    function growtype_ai_colors_groups()
    {
        $colors = array (
            "black" => array ("r" => array (0, 30), "g" => array (0, 40), "b" => array (0, 60)),
            "gray" => array ("r" => array (30, 80), "g" => array (40, 80), "b" => array (55, 80)),
            "brown" => array ("r" => array (85, 155), "g" => array (30, 100), "b" => array (0, 70)),
            "red" => array ("r" => array (150, 255), "g" => array (0, 85), "b" => array (0, 100)),
            "pink" => array ("r" => array (220, 255), "g" => array (120, 210), "b" => array (150, 235)),
            "purple" => array ("r" => array (110, 255), "g" => array (0, 130), "b" => array (100, 255)),
            "green" => array ("r" => array (0, 100), "g" => array (100, 255), "b" => array (0, 100)),
            "blue" => array ("r" => array (0, 60), "g" => array (0, 150), "b" => array (130, 255)),
            "yellow" => array ("r" => array (200, 255), "g" => array (190, 255), "b" => array (0, 140)),
            "orange" => array ("r" => array (240, 255), "g" => array (100, 255), "b" => array (0, 145)),
            "lightgrey" => array ("r" => array (90, 192), "g" => array (100, 192), "b" => array (110, 192)),
            "white" => array ("r" => array (190, 255), "g" => array (190, 255), "b" => array (190, 255)),
        );

        return $colors;
    }
}

if (!function_exists('growtype_ai_color_code_to_group')) {
    function growtype_ai_color_code_to_group($color_code)
    {
        list($r, $g, $b) = sscanf($color_code, "#%02x%02x%02x");

        $colors = growtype_ai_colors_groups();

        foreach ($colors as $color => $ranges) {
            if ($r >= $ranges["r"][0] && $r <= $ranges["r"][1] &&
                $g >= $ranges["g"][0] && $g <= $ranges["g"][1] &&
                $b >= $ranges["b"][0] && $b <= $ranges["b"][1]) {
                return $color;
            }
        }

        return null;
    }
}

/**
 * @param $hex
 * @param $opacity
 * @return string
 */
if (!function_exists('growtype_ai_hex_to_rgb')) {
    function growtype_ai_hex_to_rgb($hex, $opacity = 1)
    {
        $rgb_values = list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");

        return !empty($rgb_values) ? 'rgb(' . implode(' ', $rgb_values) . '/' . $opacity . ')' : '';
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_character_details')) {
    function growtype_ai_get_model_character_details($model_id)
    {
        $settings = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model_id],
            ]
        ]);

        $character_details = [];
        foreach ($settings as $key => $setting) {
            if (in_array($setting['meta_key'], array_keys(growtype_ai_get_model_character_default_data()))) {
                $character_details[$key] = $setting;
            }
        }

        return $character_details;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_character_default_data')) {
    function growtype_ai_get_model_character_default_data()
    {
        return [
            'character_gpt_personality_extension' => '',
            'character_intro_message' => "",
            'character_can_answer_to_questions' => "",
            'character_title' => "Olivia Wright",
            'character_description' => "A traveler with an insatiable wanderlust for both the world and the senses.",
            'character_introduction' => "Hi, my name is Olivia. I am a nurse and love to help people. Im Canadian and currently live in Toronto. I dream to help people all around the world. I love to chat about gardening, books, and music. Lets have a chat 🌟💖",
            'character_personality' => "Nurturing, Thoughtful",
            'character_occupation' => "Nurse",
            'character_hobbies' => "Gardening, Reading",
            'character_body_shape' => "Hourglass",
            'character_age' => "22",
            'character_height' => "165",
            'character_weight' => "58",
            'character_nationality' => "American",
            'character_location' => "Los Angeles",
            'character_gender' => "Female",
            'character_dreams' => "Finding True Love",
            'character_style' => "realistic",
            'character_ethnicity' => "caucasian",
            'character_eye_color' => "blue",
            'character_hair_style' => "bangs",
            'character_hair_color' => "",
            'character_breast_size' => "medium",
            'character_butt_size' => "medium",
        ];
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_featured_in_options')) {
    function growtype_ai_get_model_featured_in_options()
    {
        return [
            'artdecorio' => 'artdecorio.com',
            'talkiemate' => 'talkiemate.com',
        ];
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_ai_get_model_users_options')) {
    function growtype_ai_get_model_users_options()
    {
        return [
            'admin' => 'Admin',
            'external_user' => 'External User',
        ];
    }
}

/**
 *
 */
if (!function_exists('growtype_ai_compress_existing_image')) {
    function growtype_ai_compress_existing_image($image_id)
    {
        $image_path = growtype_ai_get_image_path($image_id);

        if (!empty($image_path) && file_exists($image_path)) {
            try {
                $image_details = growtype_ai_get_image_details($image_id);

                if (in_array($image_details['extension'], ['jpg', 'png'])) {
                    if (!isset($image_details['settings']['compressed'])) {
                        error_log(sprintf('Compressing image %s', $image_details['name'] . '.' . $image_details['extension']));

                        $resmush = new Resmush_Crud();
                        $img_path = growtype_ai_get_image_url($image_id);
                        $img_url = !empty($img_path) ? $resmush->compress_online($img_path) : '';

                        if (!empty($img_url)) {
                            unlink($image_path);

                            growtype_ai_save_external_file([
                                'location' => 'locally',
                                'url' => $img_url,
                                'name' => $image_details['name'],
                                'extension' => $image_details['extension'],
                            ], $image_details['folder']);

                            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                'image_id' => $image_id,
                                'meta_key' => 'compressed',
                                'meta_value' => 'true',
                            ]);
                        }
                    } else {
                        error_log(sprintf('Image already compressed. Image %s', $image_details['name'] . '.' . $image_details['extension']));
                    }
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
}

if (!function_exists('growtype_ai_get_featured_in_group_images')) {
    function growtype_ai_get_featured_in_group_images($groups, $created_by_options, $unique_hashes)
    {
        global $wpdb;

        $like_sql = '';
        foreach ($groups as $key => $group) {
            if ($key === 0) {
                $like_sql .= "MS.meta_value LIKE '%$group%'";
            } else {
                $like_sql .= "OR MS.meta_value LIKE '%$group%'";
            }
        }

        if (empty($like_sql)) {
            return [];
        }

        $created_by_sql = '';
        foreach ($created_by_options as $key => $created_by) {
            if ($key === 0) {
                $created_by_sql .= "MS2.meta_value = '$created_by'";
            } else {
                $created_by_sql .= "OR MS2.meta_value = '$created_by'";
            }
        }

        if (empty($created_by_sql)) {
            return [];
        }

        $unique_hashes_sql = '';
        if (!empty($unique_hashes)) {
            $unique_hashes_sql = "and (MS3.meta_value = '" . implode("' OR MS3.meta_value = '", $unique_hashes) . "')";
        }

        $models_settings = $wpdb->get_results("SELECT * FROM wp_growtype_ai_model_settings as ST
WHERE ST.model_id 
IN (
SELECT ST.model_id FROM wp_growtype_ai_model_settings as ST
LEFT JOIN wp_growtype_ai_model_settings as MS ON (ST.model_id = MS.model_id AND MS.meta_key='featured_in')
LEFT JOIN wp_growtype_ai_model_settings as MS2 ON (ST.model_id = MS2.model_id AND MS2.meta_key='created_by')
LEFT JOIN wp_growtype_ai_model_settings as MS3 ON (ST.model_id = MS3.model_id AND MS3.meta_key='created_by_unique_hash')
WHERE ST.model_id > ''
and
($like_sql)
and
($created_by_sql)
$unique_hashes_sql
group by ST.model_id
)", ARRAY_A);

        $images_with_settings = $wpdb->get_results("SELECT MI.model_id, 
MI.image_id, 
IM.folder, 
IM.name, 
IM.extension,
IMGS.meta_value as nsfw, 
IMGS2.meta_value as is_featured,
IMGS3.meta_value as is_cover
FROM wp_growtype_ai_model_image as MI
left join wp_growtype_ai_images as IM on MI.image_id=IM.id
LEFT JOIN wp_growtype_ai_image_settings AS IMGS ON (MI.image_id = IMGS.image_id AND IMGS.meta_key='nsfw')
LEFT JOIN wp_growtype_ai_image_settings AS IMGS2 ON (MI.image_id = IMGS2.image_id AND IMGS2.meta_key='is_featured')
LEFT JOIN wp_growtype_ai_image_settings AS IMGS3 ON (MI.image_id = IMGS3.image_id AND IMGS3.meta_key='is_cover')
WHERE MI.model_id 
IN (
SELECT model_id FROM wp_growtype_ai_model_settings as ST where ST.meta_key='featured_in' and ST.meta_value LIKE '%talkiemate%' group by ST.model_id
) group by MI.image_id", ARRAY_A);

        $images_wth_meta = [];
        foreach ($images_with_settings as $image) {
            $images_wth_meta[$image['model_id']][$image['image_id']]['folder'] = $image['folder'];
            $images_wth_meta[$image['model_id']][$image['image_id']]['name'] = $image['name'];
            $images_wth_meta[$image['model_id']][$image['image_id']]['extension'] = $image['extension'];
            $images_wth_meta[$image['model_id']][$image['image_id']]['nsfw'] = !empty($image['nsfw']) ? $image['nsfw'] : false;
            $images_wth_meta[$image['model_id']][$image['image_id']]['is_featured'] = !empty($image['is_featured']) ? $image['is_featured'] : false;
            $images_wth_meta[$image['model_id']][$image['image_id']]['is_cover'] = !empty($image['is_cover']) ? $image['is_cover'] : false;
        }

        $images_grouped = [];
        foreach ($images_wth_meta as $model_id => $images) {
            foreach ($images as $image_id => $image) {
                $group_name = [];

                if (isset($image['nsfw']) && $image['nsfw']) {
                    $group_name[] = 'private_images';
                } else {
                    if (isset($image['is_featured']) && $image['is_featured']) {
                        $group_name[] = 'featured_images';
                    }
                    if (isset($image['is_cover']) && $image['is_cover']) {
                        $group_name[] = 'cover_images';
                    }
                }

                if (empty($group_name)) {
                    $group_name[] = 'public_images';
                }

                if (!isset($image['folder'])) {
                    continue;
                }

                foreach ($group_name as $group_name_single) {
                    $images_grouped[$model_id][$group_name_single][$image_id] = [
                        'url' => growtype_ai_build_local_image_url($image)
                    ];
                }
            }
        }

        $profile_keys = growtype_ai_get_model_character_default_data();

        $required_keys = array_merge(array_keys($profile_keys), [
            'slug',
            'categories',
            'tags',
            'created_by',
            'created_by_unique_hash',
            'model_is_private',
        ]);

        $return_data = [];
        foreach ($models_settings as $model) {
            if (!in_array($model['meta_key'], $required_keys)) {
                continue;
            }

            $return_data[$model['model_id']]['id'] = $model['model_id'];

            if (in_array($model['meta_key'], array_keys($profile_keys))) {
                $return_data[$model['model_id']]['details'][$model['meta_key']] = $model['meta_value'];
            } else {
                $return_data[$model['model_id']][$model['meta_key']] = $model['meta_value'];
            }

            if (!isset($return_data[$model['model_id']]["public_images"]) && isset($images_grouped[$model['model_id']]["public_images"])) {
                $return_data[$model['model_id']]["public_images"] = array_values($images_grouped[$model['model_id']]["public_images"]);
            }

            if (!isset($return_data[$model['model_id']]["private_images"]) && isset($images_grouped[$model['model_id']]["private_images"])) {
                $return_data[$model['model_id']]["private_images"] = array_values($images_grouped[$model['model_id']]["private_images"]);
            }

            if (!isset($return_data[$model['model_id']]["featured_images"]) && isset($images_grouped[$model['model_id']]["featured_images"])) {
                $return_data[$model['model_id']]["featured_images"] = array_values($images_grouped[$model['model_id']]["featured_images"]);
            }

            if (!isset($return_data[$model['model_id']]["cover_images"]) && isset($images_grouped[$model['model_id']]["cover_images"])) {
                $return_data[$model['model_id']]["cover_images"] = array_values($images_grouped[$model['model_id']]["cover_images"]);
            }

            $return_data[$model['model_id']]['private_images_count'] = isset($return_data[$model['model_id']]['private_images']) ? count($return_data[$model['model_id']]['private_images']) : 0;
            $return_data[$model['model_id']]['public_images_count'] = isset($return_data[$model['model_id']]['public_images']) ? count($return_data[$model['model_id']]['public_images']) : 0;
            $return_data[$model['model_id']]['featured_images_count'] = isset($return_data[$model['model_id']]['featured_images']) ? count($return_data[$model['model_id']]['featured_images']) : 0;

            if ($model['meta_key'] === 'categories') {
                $return_data[$model['model_id']]['categories'] = !empty($model['meta_value']) ? json_decode(stripslashes($model['meta_value']), true) : [];
            }

            if ($model['meta_key'] === 'tags') {
                $return_data[$model['model_id']]['tags'] = !empty($model['meta_value']) ? json_decode(stripslashes($model['meta_value']), true) : [];
            }

            if ($model['meta_key'] === 'created_by') {
                $return_data[$model['model_id']]['created_by'] = !empty($model['meta_value']) ? $model['meta_value'] : '';
            }

            if ($model['meta_key'] === 'created_by_unique_hash') {
                $return_data[$model['model_id']]['created_by_unique_hash'] = !empty($model['meta_value']) ? $model['meta_value'] : '';
            }

            if ($model['meta_key'] === 'model_is_private') {
                $return_data[$model['model_id']]['model_is_private'] = !empty($model['meta_value']) ? $model['meta_value'] : false;
            }

            if (!isset($return_data[$model['model_id']]['updated_at']) || empty($return_data[$model['model_id']]['updated_at'])) {
                $return_data[$model['model_id']]['updated_at'] = $model['updated_at'] ?? '';
            }

            if (!isset($return_data[$model['model_id']]['created_at']) || empty($return_data[$model['model_id']]['created_at'])) {
                $return_data[$model['model_id']]['created_at'] = $model['created_at'] ?? '';
            }
        }

        return $return_data;
    }
}
