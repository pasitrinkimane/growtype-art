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
if (!function_exists('growtype_art_include_view')) {
    function growtype_art_include_view($file_path, $variables = array ())
    {
        $fallback_view = GROWTYPE_ART_PATH . 'resources/views/' . str_replace('.', '/', $file_path) . '.php';
        $child_blade_view = get_stylesheet_directory() . '/views/' . GROWTYPE_ART_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.blade.php';
        $child_view = get_stylesheet_directory() . '/views/' . GROWTYPE_ART_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.php';

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
if (!function_exists('growtype_art_load_textdomain')) {
    function growtype_art_load_textdomain($lang)
    {
        global $q_config;

        if (isset($q_config['locale'][$lang])) {
            load_textdomain('growtype-art', GROWTYPE_ART_PATH . 'languages/growtype-art-' . $q_config['locale'][$lang] . '.mo');
        }
    }
}

if (!function_exists('growtype_art_save_local_file')) {
    function growtype_art_save_local_file($file, $folder_name)
    {
        // Get the upload directory
        $growtype_art_upload_dir = growtype_art_get_upload_dir($folder_name);

        // Ensure the directory exists or create it
        if (!file_exists($growtype_art_upload_dir)) {
            if (!wp_mkdir_p($growtype_art_upload_dir)) {
                return ['error' => 'Failed to create upload directory'];
            }
        }

        $file_extension = $file['extension'] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
        $sanitized_name = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
        $save_file_loc = trailingslashit($growtype_art_upload_dir) . $sanitized_name . '.' . $file_extension;

        $save_details = [
            'path' => $save_file_loc,
            'url' => growtype_art_get_upload_dir_public($folder_name) . '/' . $sanitized_name . '.' . $file_extension
        ];

        // Handle Base64-encoded images
        if (isset($file['tmp_file'])) {
            if (rename($file['tmp_file'], $save_file_loc)) {
                return $save_details;
            } else {
                return ['error' => 'Failed to save the Base64 image'];
            }
        }

        if (isset($file['tmp_name'])) {
            if (move_uploaded_file($file['tmp_name'], $save_file_loc)) {
                return $save_details;
            } else {
                return ['error' => 'Failed to save the uploaded file'];
            }
        }

        return ['error' => 'Invalid file input'];
    }
}

/**
 * Save external image
 */
if (!function_exists('growtype_art_save_external_file')) {
    function growtype_art_save_external_file($file, $folder_name = null)
    {
        $saving_location = $file['location'] ?? 'locally';

        switch ($saving_location) {
            case 'locally':
                $growtype_art_upload_dir = growtype_art_get_upload_dir($folder_name);

                if (!file_exists($growtype_art_upload_dir)) {
                    wp_mkdir_p($growtype_art_upload_dir);
                }

                $url = $file['url'];

                if (!isset($file['name'])) {
                    $file['name'] = wp_generate_password(34, false);
                }

                if (!isset($file['extension'])) {
                    $parsed_url = parse_url($file['url'], PHP_URL_PATH);
                    $file['extension'] = pathinfo($parsed_url, PATHINFO_EXTENSION);
                }

                $save_file_loc = $growtype_art_upload_dir . '/' . $file['name'] . '.' . $file['extension'];

                if (file_exists($growtype_art_upload_dir)) {
                    // Initialize the cURL session
                    $ch = curl_init($url);

                    // Open file
                    $fp = fopen($save_file_loc, 'wb');

                    // Set cURL options
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
                    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);         // Maximum redirects to follow
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);          // Set timeout limit

                    // Custom Headers
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36",
                        "Accept: image/jpeg,image/png,image/gif,image/webp,*/*;q=0.8",
                        "Connection: keep-alive"
                    ]);

                    // Perform a cURL session
                    curl_exec($ch);

                    // Check for errors
                    if (curl_errno($ch)) {
                        $error_msg = curl_error($ch);
                        error_log('cURL error: ' . $error_msg);
                    }

                    // Closes a cURL session and frees all resources
                    curl_close($ch);

                    // Close file
                    fclose($fp);
                }

                return [
                    'path' => $save_file_loc,
                    'url' => growtype_art_get_upload_dir_public($folder_name) . '/' . $file['name'] . '.' . $file['extension'],
                ];

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
if (!function_exists('growtype_art_get_external_folder_url')) {
    function growtype_art_get_external_folder_url($folder_name)
    {
        $cloudinary_crud = new Cloudinary_Crud();
        return $cloudinary_crud->get_folder_url($folder_name);
    }
}

/**
 * Save image
 */
if (!function_exists('growtype_art_get_model_images_grouped')) {
    function growtype_art_get_model_images_grouped($model_id, $limit = 10, $offset = 0)
    {
        global $wpdb;

        $models_table = $wpdb->prefix . 'growtype_art_models';
        $pivot_table = $wpdb->prefix . 'growtype_art_model_image';
        $images_table = $wpdb->prefix . 'growtype_art_images';
        $settings_table = $wpdb->prefix . 'growtype_art_image_settings';

        // Step 1: Get 10 distinct image IDs ordered by created_at DESC
        $image_ids_query = Growtype_Art_Database_Crud::custom_query("
        SELECT i.id
        FROM {$models_table} AS m
        INNER JOIN {$pivot_table} AS mi ON mi.model_id = m.id
        INNER JOIN {$images_table} AS i ON i.id = mi.image_id
        WHERE m.id = %d
        ORDER BY i.created_at DESC
        LIMIT %d OFFSET %d
    ", [$model_id, $limit, $offset]);

        $image_ids = array_column($image_ids_query, 'id');

        if (empty($image_ids)) {
            return ['original' => [], 'faceswap' => []];
        }

        // Step 2: Fetch full image data with settings for those IDs
        $placeholders = implode(',', array_fill(0, count($image_ids), '%d'));

        $raw_results = Growtype_Art_Database_Crud::custom_query("
        SELECT 
            i.id AS image_id,
            i.name,
            i.extension,
            i.width,
            i.height,
            i.location,
            i.folder,
            i.reference_id,
            i.created_at,
            i.updated_at,
            mi.model_id,
            s.meta_key,
            s.meta_value
        FROM {$images_table} AS i
        INNER JOIN {$pivot_table} AS mi ON mi.image_id = i.id
        LEFT JOIN {$settings_table} AS s ON s.image_id = i.id
        WHERE i.id IN ($placeholders)
        ORDER BY i.created_at DESC
    ", $image_ids);

        // Step 3: Group and format results
        $grouped = [
            'original' => [],
            'faceswap' => []
        ];

        foreach ($raw_results as $row) {
            $id = $row['image_id'];

            // Initialize image entry if not already set
            if (!isset($grouped['original'][$id]) && !isset($grouped['faceswap'][$id])) {
                $image_data = [
                    'id' => $id,
                    'name' => $row['name'],
                    'extension' => $row['extension'],
                    'width' => $row['width'],
                    'height' => $row['height'],
                    'location' => $row['location'],
                    'folder' => $row['folder'],
                    'reference_id' => $row['reference_id'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'model_id' => $row['model_id'],
                    'settings' => [],
                ];

                $target = (strpos($row['name'], 'faceswap') !== false) ? 'faceswap' : 'original';
                $grouped[$target][$id] = $image_data;
            }

            // Append settings
            if (!empty($row['meta_key'])) {
                $target = (strpos($row['name'], 'faceswap') !== false) ? 'faceswap' : 'original';
                $grouped[$target][$id]['settings'][$row['meta_key']] = $row['meta_value'];
            }
        }

        // Reindex arrays
        $grouped['original'] = array_values($grouped['original']);
        $grouped['faceswap'] = array_values($grouped['faceswap']);

        return $grouped;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_model_details')) {
    function growtype_art_get_model_details($model_id)
    {
        if (!$model_id) {
            return null;
        }

        global $wpdb;

        // Use explicit table names with wp_ prefix
        $models_table = $wpdb->prefix . 'growtype_art_models';
        $settings_table = $wpdb->prefix . 'growtype_art_model_settings';

        // Fetch model and settings in one query
        $results = Growtype_Art_Database_Crud::custom_query("
        SELECT 
            m.id,
            m.prompt,
            m.negative_prompt,
            m.reference_id,
            m.image_folder,
            m.provider,
            m.created_at,
            m.updated_at,
            ms.meta_key,
            ms.meta_value
        FROM {$models_table} AS m
        LEFT JOIN {$settings_table} AS ms ON m.id = ms.model_id
        WHERE m.id = %d
    ", [$model_id]);

        if (empty($results)) {
            return null;
        }

        // Initialize model from first row
        $first = $results[0];
        $model = [
            'id' => $first['id'],
            'prompt' => $first['prompt'],
            'negative_prompt' => $first['negative_prompt'],
            'reference_id' => $first['reference_id'],
            'image_folder' => $first['image_folder'],
            'provider' => $first['provider'],
            'created_at' => $first['created_at'],
            'updated_at' => $first['updated_at'],
            'settings' => []
        ];

        // Aggregate settings efficiently
        foreach ($results as $row) {
            if (!empty($row['meta_key'])) {
                $model['settings'][$row['meta_key']] = $row['meta_value'];
            }
        }

        return $model;
    }
}

function growtype_art_get_model_total_images_amount($model_id)
{
    global $wpdb;

    return (int)$wpdb->get_var(
        $wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}growtype_art_model_image 
        WHERE model_id = %d
    ", $model_id)
    );
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_image_details')) {
    function growtype_art_get_image_details($image_id)
    {
        $image = Growtype_Art_Database_Crud::get_single_record(Growtype_Art_Database::IMAGES_TABLE, [
            [
                'key' => 'id',
                'values' => [$image_id],
            ]
        ]);

        if (empty($image)) {
            return null;
        }

        $model_details = growtype_art_get_image_model_details($image['id']);

        if (empty($model_details)) {
            return null;
        }

        $image['model_id'] = growtype_art_get_image_model_details($image['id'])['id'];

        $models_settings = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
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
if (!function_exists('growtype_art_get_image_model_details')) {
    function growtype_art_get_image_model_details($image_id)
    {
        $image_model = Growtype_Art_Database_Crud::get_single_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
            [
                'key' => 'image_id',
                'values' => [$image_id],
            ]
        ]);

        if (empty($image_model)) {
            return null;
        }

        return growtype_art_get_model_details($image_model['model_id']);
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_generate_reference_id')) {
    function growtype_art_generate_reference_id()
    {
        return md5(uniqid() . uniqid());
    }
}

if (!function_exists('growtype_art_init_job')) {
    function growtype_art_init_job($job_name, $payload, $delay = 5)
    {
        growtype_cron_init_job($job_name, $payload, $delay);
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_delete_model_images')) {
    function growtype_art_delete_model_images($model_id)
    {
        $model_images = growtype_art_get_model_images_grouped($model_id)['original'] ?? [];

        $model_image = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model_id],
            ]
        ]);

        Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::IMAGES_TABLE, array_pluck($model_images, 'id'));
        Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, array_pluck($model_image, 'id'));
    }
}

/**
 * Model setting
 */
if (!function_exists('growtype_art_get_model_single_setting')) {
    function growtype_art_get_model_single_setting($model_id, $setting_key)
    {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'growtype_art_model_settings';

        $result = $wpdb->get_row(
            $wpdb->prepare("
            SELECT *
            FROM {$settings_table}
            WHERE model_id = %d AND meta_key = %s
            LIMIT 1
        ", $model_id, $setting_key),
            ARRAY_A
        );

        return $result ?: null;
    }
}

if (!function_exists('growtype_art_content_upload_folder_name')) {
    function growtype_art_content_upload_folder_name()
    {
        return 'growtype-ai-uploads';
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_upload_dir')) {
    function growtype_art_get_upload_dir($folder_name = null)
    {
        $upload_dir = wp_upload_dir();

        return !empty($folder_name) ? $upload_dir['basedir'] . '/' . growtype_art_content_upload_folder_name() . '/' . $folder_name : $upload_dir['basedir'] . '/' . growtype_art_content_upload_folder_name();
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_image_local_path')) {
    function growtype_art_image_local_path($image)
    {
        return growtype_art_get_upload_dir($image['folder']) . '/' . $image['name'] . '.' . $image['extension'];
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_upload_dir_public')) {
    function growtype_art_get_upload_dir_public($folder_name = null)
    {
        $upload_dir = wp_upload_dir();
        $upload_dir = $upload_dir['baseurl'];

        return !empty($folder_name) ? $upload_dir . '/' . growtype_art_content_upload_folder_name() . '/' . $folder_name : $upload_dir . '/' . growtype_art_content_upload_folder_name();
    }
}

/**
 * Image
 */
if (!function_exists('growtype_art_get_image_url')) {
    function growtype_art_get_image_url($image_id)
    {
        $image = growtype_art_get_image_details($image_id);

        if (empty($image)) {
            return '';
        }

        $location = isset($image['location']) && !empty($image['location']) ? $image['location'] : 'locally';

        $img_url = '';

        if ($location === 'locally') {
            $img_url = growtype_art_build_public_image_url($image);
        } elseif ($location === 'cloudinary') {
            $img_url = 'https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
        }

        return $img_url;
    }
}

if (!function_exists('growtype_art_build_public_image_url')) {
    function growtype_art_build_public_image_url($image)
    {
        return growtype_art_get_upload_dir_public() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
    }
}

/**
 * Image
 */
if (!function_exists('growtype_art_get_image_path')) {
    function growtype_art_get_image_path($image_id)
    {
        $image = growtype_art_get_image_details($image_id);

        if (empty($image)) {
            return null;
        }

        return growtype_art_get_upload_dir() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
    }
}

/**
 * Public id
 */
if (!function_exists('growtype_art_get_cloudinary_public_id')) {
    function growtype_art_get_cloudinary_public_id($image)
    {
        return $image['folder'] . '/' . $image['name'];
    }
}

/**
 * Public id
 */
if (!function_exists('growtype_art_get_images_saving_location')) {
    function growtype_art_get_images_saving_location()
    {
        return get_option('growtype_art_images_saving_location', 'locally');
    }
}

/**
 * Public id
 */
if (!function_exists('growtype_art_get_art_categories')) {
    function growtype_art_get_art_categories()
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

if (!function_exists('growtype_art_colors_groups')) {
    function growtype_art_colors_groups()
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

if (!function_exists('growtype_art_color_code_to_group')) {
    function growtype_art_color_code_to_group($color_code)
    {
        list($r, $g, $b) = sscanf($color_code, "#%02x%02x%02x");

        $colors = growtype_art_colors_groups();

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
if (!function_exists('growtype_art_hex_to_rgb')) {
    function growtype_art_hex_to_rgb($hex, $opacity = 1)
    {
        $rgb_values = list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");

        return !empty($rgb_values) ? 'rgb(' . implode(' ', $rgb_values) . '/' . $opacity . ')' : '';
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_model_character_details')) {
    function growtype_art_get_model_character_details($model_id)
    {
        $settings = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model_id],
            ]
        ]);

        $character_details = [];
        foreach ($settings as $key => $setting) {
            if (in_array($setting['meta_key'], array_keys(growtype_art_get_model_character_default_data()))) {
                $character_details[$key] = $setting;
            }
        }

        return $character_details;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_model_character_default_data')) {
    function growtype_art_get_model_character_default_data()
    {
        return [
            'character_gpt_personality_extension' => '',
            'character_intro_message' => "",
            'character_can_answer_to_questions' => "",
            'character_popular_topics_to_discuss' => "",
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
if (!function_exists('growtype_art_get_model_featured_in_options')) {
    function growtype_art_get_model_featured_in_options()
    {
        return [
            'artdecorio' => 'artdecorio.com',
            'talkiemate' => 'talkiemate.com',
            'chataigirl' => 'chataigirl.com',
            'aiwinbig' => 'aiwinbig.com',
            'liamcompanion' => 'liamcompanion.com',
        ];
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_model_provider_options')) {
    function growtype_art_get_model_provider_options()
    {
        return Growtype_Art_Crud::MODEL_GENERATE_IMAGE_PROVIDERS;
    }
}

/**
 * Model details
 */
if (!function_exists('growtype_art_get_model_users_options')) {
    function growtype_art_get_model_users_options()
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
if (!function_exists('growtype_art_compress_existing_image')) {
    function growtype_art_compress_existing_image($image_id)
    {
        $image_path = growtype_art_get_image_path($image_id);

        if (empty($image_path) || !file_exists($image_path)) {
            error_log("Image path invalid or file does not exist for image ID: $image_id");
            return;
        }

        try {
            $image_details = growtype_art_get_image_details($image_id);
            $extension = strtolower($image_details['extension'] ?? '');

            if (!in_array($extension, ['jpg', 'png'])) {
                error_log("Unsupported image format: $extension for image ID: $image_id");
                throw new Exception("Unsupported image format.");
                return;
            }

            $is_compressed = $image_details['settings']['compressed'] ?? false;
            if ($is_compressed) {
                error_log("Image already compressed: {$image_details['name']}.$extension");
                return;
            }

            error_log("Compressing image: {$image_details['name']}.$extension");

            $resmush = new Resmush_Crud();
            $img_url = $resmush->compress_online(growtype_art_get_image_url($image_id));

            if (empty($img_url)) {
                error_log("Compression failed or returned empty URL for image ID: $image_id");
                return;
            }

            // Remove original image
            if (!unlink($image_path)) {
                error_log("Failed to delete original image at path: $image_path");
                return;
            }

            // Save compressed image
            growtype_art_save_external_file([
                'location' => 'locally',
                'url' => $img_url,
                'name' => $image_details['name'],
                'extension' => $extension,
            ], $image_details['folder']);

            // Mark image as compressed
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'compressed',
                'meta_value' => 'true',
            ]);

        } catch (Exception $e) {
            error_log("Exception during image compression: " . $e->getMessage());
        }
    }
}

if (!function_exists('growtype_art_get_featured_in_group_models')) {
    function growtype_art_get_featured_in_group_models($params = [])
    {
        global $wpdb;

        // Extract parameters with defaults
        $groups = $params['groups'] ?? [];
        $created_by_options = $params['created_by_options'] ?? ['admin', 'external_user'];
        $unique_hashes = $params['unique_hashes'] ?? [];
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
        $characters_slugs = $params['characters_slugs'] ?? [];
        $character_occupation = $params['character_occupation'] ?? '';
        $models_ids = $params['models_ids'] ?? [];
        $optimized_images = $params['optimized_images'] ?? [];
        $tags = $params['tags'] ?? [];

        // Dynamically build SQL conditions
        $conditions = [];
        $query_params = [];

        // Groups condition
        if (!empty($groups)) {
            foreach ($groups as $group) {
                // Match against any value inside the JSON array
                $conditions[] = "MS.meta_value LIKE %s";
                $query_params[] = '%' . $group . '%';
            }

            // Optional: group conditions with OR
            if (count($groups) > 1) {
                $conditions[] = '(' . implode(' OR ', array_fill(0, count($groups), "MS.meta_value LIKE %s")) . ')';
                $query_params = array_merge($query_params, array_map(fn($g) => "%$g%", $groups));
            }
        }

        // Created by options condition
        if (!empty($created_by_options)) {
            $created_by_placeholders = implode(',', array_fill(0, count($created_by_options), '%s'));
            $conditions[] = "MS2.meta_value IN ($created_by_placeholders)";
            $query_params = array_merge($query_params, $created_by_options);
        }

        // Unique hashes condition
        if (!empty($unique_hashes)) {
            $unique_hashes_placeholders = implode(',', array_fill(0, count($unique_hashes), '%s'));
            $conditions[] = "MS3.meta_value IN ($unique_hashes_placeholders)";
            $query_params = array_merge($query_params, $unique_hashes);
        }

        // Character slugs condition
        if (!empty($characters_slugs)) {
            $characters_slugs_placeholders = implode(',', array_fill(0, count($characters_slugs), '%s'));
            $conditions[] = "MS4.meta_value IN ($characters_slugs_placeholders)";
            $query_params = array_merge($query_params, $characters_slugs);
        }

        // Models IDs condition
        if (!empty($models_ids)) {
            $models_ids_placeholders = implode(',', array_fill(0, count($models_ids), '%d'));
            $conditions[] = "ST.model_id IN ($models_ids_placeholders)";
            $query_params = array_merge($query_params, $models_ids);
        }

        if (!empty($character_occupation)) {
            $conditions[] = "MS5.meta_value='$character_occupation'";
        }

        if (!empty($tags)) {
            $tagConditions = [];
            foreach ($tags as $tag) {
                // Prepare a condition that checks if MS6.meta_value JSON array contains the tag.
                // Use JSON_ENCODE to ensure the tag is properly formatted as a JSON string.
                $tagConditions[] = "JSON_CONTAINS(LOWER(MS6.meta_value), LOWER(%s)) = 1";
                $query_params[] = json_encode($tag);
            }
            // Join the individual JSON_CONTAINS conditions with OR (adjust as needed)
            $conditions[] = '(' . implode(' OR ', $tagConditions) . ')';
        }

        // Combine all conditions
        $where_sql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Base query for fetching models
        $query_settings = "
        SELECT ST.*, MO.*
        FROM wp_growtype_art_model_settings AS ST
        LEFT JOIN wp_growtype_art_models AS MO ON ST.model_id = MO.id
        INNER JOIN (
            SELECT DISTINCT ST.model_id
            FROM wp_growtype_art_model_settings AS ST
            LEFT JOIN wp_growtype_art_model_settings AS MS ON (ST.model_id = MS.model_id AND MS.meta_key = 'featured_in')
            LEFT JOIN wp_growtype_art_model_settings AS MS2 ON (ST.model_id = MS2.model_id AND MS2.meta_key = 'created_by')
            LEFT JOIN wp_growtype_art_model_settings AS MS3 ON (ST.model_id = MS3.model_id AND MS3.meta_key = 'created_by_unique_hash')
            LEFT JOIN wp_growtype_art_model_settings AS MS4 ON (ST.model_id = MS4.model_id AND MS4.meta_key = 'slug')
            LEFT JOIN wp_growtype_art_model_settings AS MS5 ON (ST.model_id = MS5.model_id AND MS5.meta_key = 'character_occupation')
            LEFT JOIN wp_growtype_art_model_settings AS MS6 ON (ST.model_id = MS6.model_id AND MS6.meta_key = 'tags')
            $where_sql
            LIMIT %d OFFSET %d
        ) AS limited_models ON ST.model_id = limited_models.model_id
    ";

        $query_params[] = $limit;
        $query_params[] = $offset;

        $models_settings = $wpdb->get_results($wpdb->prepare($query_settings, $query_params), ARRAY_A);

        $image_query_params = array_column($models_settings, 'model_id');

        if (empty($image_query_params)) {
            return [];
        }

        $image_query_placeholders = implode(',', array_fill(0, count($image_query_params), '%d'));
        $query_image_with_settings = "
        SELECT MI.model_id, MI.image_id, IM.folder, IM.name, IM.extension, IM.width, IM.height,
               MAX(CASE WHEN IMGS.meta_key = 'nsfw' THEN IMGS.meta_value END) AS nsfw,
               MAX(CASE WHEN IMGS.meta_key = 'is_featured' THEN IMGS.meta_value END) AS is_featured,
               MAX(CASE WHEN IMGS.meta_key = 'is_cover' THEN IMGS.meta_value END) AS is_cover,
               MAX(CASE WHEN IMGS.meta_key = 'private' THEN IMGS.meta_value END) AS private,
               MAX(CASE WHEN IMGS.meta_key = 'generation_id' THEN IMGS.meta_value END) AS generation_id,
               MAX(CASE WHEN IMGS.meta_key = 'nudity' THEN IMGS.meta_value END) AS nudity,
        GROUP_CONCAT(
            CASE WHEN IMGS.meta_key LIKE 'video_url%' 
            THEN CONCAT(IMGS.meta_key, ':', IMGS.meta_value) 
            END 
            SEPARATOR '||'
        ) AS video_urls
        FROM wp_growtype_art_model_image AS MI
        LEFT JOIN wp_growtype_art_images AS IM ON MI.image_id = IM.id
        LEFT JOIN wp_growtype_art_image_settings AS IMGS ON MI.image_id = IMGS.image_id
        WHERE MI.model_id IN ($image_query_placeholders)
        GROUP BY MI.model_id, MI.image_id
    ";

        $images_with_settings = $wpdb->get_results($wpdb->prepare($query_image_with_settings, $image_query_params), ARRAY_A);

        $images_grouped = [];
        foreach ($images_with_settings as $image) {
            $model_id = $image['model_id'];
            $image_id = $image['image_id'];

            if (isset($image['private']) && $image['private']) {
                $group_name = 'private_images';
            } elseif (str_contains($image['name'], 'thumbnail')) {
                $group_name = 'thumbnail_images';
            } elseif ($image['is_cover']) {
                $group_name = 'cover_images';
            } elseif ($image['is_featured']) {
                $group_name = 'featured_images';
            } elseif ($image['nudity']) {
                $group_name = 'naked_images';
            } elseif ($image['nsfw']) {
                $group_name = 'erotic_images';
            } else {
                $group_name = 'public_images';
            }

            $url = growtype_art_build_public_image_url($image);

            if ($optimized_images) {
                /**
                 * Check webp
                 */
                $url = growtype_art_image_get_alternative_format($url);
            }

            if (!empty($url)) {
                $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                if ($extension === 'mp4') {
                    $group_name = str_replace('_images', '_videos', $group_name);
                }
            }

            $video_urls = $image['video_urls'] ?? [];
            $video_urls_exploded = [];

            if (!empty($video_urls)) {
                $video_entries = explode('||', $video_urls);

                foreach ($video_entries as $entry) {
                    if (preg_match('/video_url_image_id_(\d+):(.*)/', $entry, $matches)) {
                        $video_urls_exploded[] = [
                            'id' => $matches[1],
                            'url' => trim($matches[2])
                        ];
                    }
                }
            }

            $images_grouped[$model_id][$group_name][$image_id] = [
                'url' => $url,
                'width' => $image['width'] ?? '',
                'height' => $image['height'] ?? '',
                'generation_id' => $image['generation_id'] ?? '',
                'image_id' => $image_id,
                'video_urls' => $video_urls_exploded,
            ];
        }

        $profile_keys = growtype_art_get_model_character_default_data();

        $required_keys = array_merge(array_keys($profile_keys), [
            'slug',
            'categories',
            'tags',
            'created_by',
            'created_by_unique_hash',
            'model_is_private',
            'priority_level',
        ]);

        $json_values = [
            'categories',
            'tags',
        ];

        $boolean_values = [
            'model_is_private',
        ];

        $integer_values = [
            'priority_level',
        ];

        $empty_values = [
            'created_by',
            'created_by_unique_hash',
        ];

        $return_data = [];
        foreach ($models_settings as $model) {
            $model_id = $model['model_id'];
            $meta_key = $model['meta_key'];

            if (!in_array($meta_key, $required_keys)) {
                continue;
            }

            $return_data[$model_id]['id'] = $model_id;

            if (isset($profile_keys[$meta_key])) {
                $return_data[$model_id]['details'][$meta_key] = $model['meta_value'];
            } else {
                $return_data[$model_id][$meta_key] = $model['meta_value'];
            }

            foreach ($json_values as $json_value) {
                if ($meta_key === $json_value) {
                    $return_data[$model_id][$meta_key] = !empty($model['meta_value']) ? json_decode(stripslashes($model['meta_value']), true) : [];

                    if (!empty($return_data[$model_id][$meta_key]) && is_array($return_data[$model_id][$meta_key])) {
                        $return_data[$model_id][$meta_key] = array_map(function ($value) {
                            return is_string($value) ? trim($value) : $value; // Trim strings only
                        }, $return_data[$model_id][$meta_key]);
                    }
                }
            }

            foreach ($empty_values as $empty_value) {
                if ($meta_key === $empty_value) {
                    $return_data[$model_id][$empty_value] = !empty($model['meta_value']) ? $model['meta_value'] : '';
                }
            }

            foreach ($boolean_values as $boolean_value) {
                if ($meta_key === $boolean_value) {
                    $return_data[$model_id][$meta_key] = filter_var($model['meta_value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }

            foreach ($integer_values as $integer_value) {
                if ($meta_key === $integer_value) {
                    $return_data[$model_id][$meta_key] = !empty($model['meta_value']) ? (int)$model['meta_value'] : 0;
                }
            }

            if (!isset($return_data[$model_id]['updated_at']) || empty($return_data[$model_id]['updated_at'])) {
                $return_data[$model_id]['updated_at'] = $model['updated_at'] ?? '';
            }

            if (!isset($return_data[$model_id]['created_at']) || empty($return_data[$model_id]['created_at'])) {
                $return_data[$model_id]['created_at'] = $model['created_at'] ?? '';
            }

            foreach ([
                         'private_images',
                         'public_images',
                         'public_videos',
                         'erotic_images',
                         'erotic_videos',
                         'featured_images',
                         'featured_videos',
                         'cover_images',
                         'cover_videos',
                         'naked_images',
                         'naked_videos',
                         'thumbnail_images',
                     ] as $type) {
                if (!isset($return_data[$model_id][$type]) && isset($images_grouped[$model_id][$type])) {
                    $return_data[$model_id][$type] = array_values($images_grouped[$model_id][$type]);
                }
                $return_data[$model_id]["{$type}_count"] = count($return_data[$model_id][$type] ?? []);
            }
        }

        return $return_data;
    }
}

function growtype_art_shuffle_assoc_array($array)
{
    // Extract keys and shuffle them
    $keys = array_keys($array);
    shuffle($keys);

    // Rebuild the array using the shuffled keys
    $shuffledArray = [];
    foreach ($keys as $key) {
        $shuffledArray[$key] = $array[$key];
    }

    return $shuffledArray;
}
