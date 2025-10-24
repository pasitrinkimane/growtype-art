<?php

class Growtype_Art_Api_Image
{

    public function __construct()
    {
        add_action('wp_loaded', [$this, 'optimize_images_on_load']);
    }

    function optimize_images_on_load()
    {
        if (isset($_SERVER['REQUEST_URI']) && preg_match('/\/system\/optimize\/images-folders(\/([\w\-]+))?$/', $_SERVER['REQUEST_URI'], $matches)) {
            $user_type = $matches[2] ?? null;

            if (!empty($user_type)) {
                if ($user_type === 'all') {
                    global $wpdb;

                    $query_args = [
                        'limit' => 40,
                        'offset' => 20,
                    ];

                    $table_name = $wpdb->prefix . "growtype_art_models";

                    $query = "SELECT * FROM $table_name WHERE id > %d ORDER BY id desc LIMIT %d OFFSET %d";
                    $prepared_query = $wpdb->prepare($query, 3773, $query_args['limit'], $query_args['offset']);
                    $models = $wpdb->get_results($prepared_query, ARRAY_A);

                    foreach ($models as $model) {
                        $image_folder = $model['image_folder'];

                        $directory = growtype_art_get_upload_dir($image_folder);

                        $optimization_result = $this->optimize_images($directory);
                    }
                } else {
                    $image_folder = 'models/' . $user_type;

                    $directory = growtype_art_get_upload_dir($image_folder);

                    $optimization_result = $this->optimize_images($directory);
                }

                d($optimization_result);
            }
        }
    }

    function optimize_images($directory)
    {
        if (!is_dir($directory)) {
            return "Error: Directory does not exist.\n";
        }

        // Allowed extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png'];

        // Open the directory and get the files
        $files = scandir($directory);

        if (!$files) {
            return "Error: Unable to read directory.\n";
        }

        $response = "";
        foreach ($files as $file) {
            // Skip . and ..
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Full file path
            $file_path = $directory . DIRECTORY_SEPARATOR . $file;

            // Check if it's a valid file and has an allowed extension
            $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
            if (is_file($file_path) && in_array(strtolower($file_extension), $allowed_extensions)) {
                // Generate the .webp output file path
                $output_file = preg_replace('/\.[^.\s]+$/', '.webp', $file_path);

                // Check if the .webp file already exists
                if (!file_exists($output_file)) {
                    // Command to convert the image to .webp
                    $command = sprintf(
                        'cwebp -q 80 -m 6 -mt -af %s -o %s',
                        escapeshellarg($file_path),
                        escapeshellarg($output_file)
                    );

                    // Execute the command
                    exec($command . ' 2>&1', $output, $return_var);

                    // Check the result
                    if ($return_var === 0) {
                        $response .= "Converted: $file_path -> $output_file\n";
                    } else {
                        $response .= "Error converting: $file_path\n";
                    }
                } else {
                    $response .= "Skipping: $output_file already exists.\n";
                }
            }
        }

        return $response;
    }

    function load_methods()
    {
        /**
         * Leonardo Ai
         */
        $this->leonardoai_crud = new Leonardoai_Crud();
    }

    function generate_images_callback($data)
    {
        $service = isset($data['service']) ? $data['service'] : null;

        if ($service === Growtype_Art_Crud::LEONARDOAI_KEY) {
            $this->leonardoai_crud->generate_model_image();
        }
    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

        register_rest_route('growtype-art/v1', 'generate/(?P<service>\w+)', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array (
                $this,
                'generate_images_callback'
            ),
            'permission_callback' => function () use ($permission) {
                return $permission;
            }
        ));

        register_rest_route('growtype-art/v1', 'retrieve/images', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array (
                $this,
                'retrieve_random_images_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        register_rest_route('growtype-art/v1', 'retrieve/image/(?P<id>\d+)', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array (
                $this,
                'retrieve_image_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));
    }

    function retrieve_image_callback($data)
    {
        $image_id = isset($data['id']) ? $data['id'] : null;

        if (empty($image_id)) {
            return;
        }

        $image = growtype_art_get_image_details($image_id);

        return wp_send_json([
            'data' => $image,
        ], 200);
    }

    function retrieve_random_images_callback(WP_REST_Request $request)
    {
        $tags = $request->get_param('tags');
        $ignored_images = !empty($request->get_param('ignored_images')) ? $request->get_param('ignored_images') : [];
        $ignored_models = !empty($request->get_param('ignored_models')) ? $request->get_param('ignored_models') : [];

        $tags = is_array($tags) ? $tags : explode(',', $tags);

        $selected_images = [];

        if (!empty($tags)) {
            switch ($tags) {
                case in_array('random', $tags):
                    $images = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGES_TABLE, [
                        [
                            'limit' => 100,
                            'offset' => 0,
                            'orderby' => 'RAND()',
                        ]
                    ]);

                    break;
                default:
                    global $wpdb;

                    /**
                     * Model table
                     */
                    $table = $wpdb->prefix . Growtype_Art_Database::MODEL_SETTINGS_TABLE;

                    $query_like_string = 'SELECT * FROM ' . $table . ' WHERE meta_key = "categories" AND ';
                    $conditions = array ();
                    foreach ($tags as $tag) {
                        $conditions[] = "meta_value LIKE '%" . $tag . "%'";
                    }
                    $query_like_string .= implode(" OR ", $conditions);
                    $query_like_string .= ' ORDER BY RAND()';

                    $model_settings = $wpdb->get_results("{$query_like_string}", ARRAY_A);

                    $models_ids = array_pluck($model_settings, 'model_id');
                    $models_ids = array_diff($models_ids, $ignored_models);

                    $table = $wpdb->prefix . Growtype_Art_Database::MODEL_IMAGE_TABLE;
                    $query_string = 'SELECT * FROM ' . $table . ' WHERE model_id IN (' . implode(',', $models_ids) . ')';
                    $image_models = $wpdb->get_results("{$query_string}", ARRAY_A);

                    $images_ids = array_pluck($image_models, 'image_id');

                    $images_ids = array_slice(array_shuffle($images_ids), 0, 200);

                    $images = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGES_TABLE, [
                        [
                            'key' => 'id',
                            'values' => array_unique($images_ids),
                        ]
                    ]);

                    break;
            }

            foreach ($images as $image) {
                $image_details = growtype_art_get_image_details($image['id']);
                $image_details['url'] = growtype_art_get_image_url($image['id']);

                $model_is_ignored = false;

                if (!$model_is_ignored && !in_array($image['id'], $ignored_images)) {
                    array_push($selected_images, $image_details);
                }
            }
        }

        return wp_send_json([
            'data' => $selected_images,
        ], 200);
    }
}
