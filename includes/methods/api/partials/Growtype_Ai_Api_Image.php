<?php

class Growtype_Ai_Api_Image
{

    public function __construct()
    {

    }

    function load_methods()
    {
        /**
         * Leonardo Ai
         */
        $this->leonardo_ai_crud = new Leonardo_Ai_Crud();
    }

    function generate_images_callback($data)
    {
        $service = isset($data['service']) ? $data['service'] : null;

        if ($service === 'leonardoai') {
            $this->leonardo_ai_crud->generate_model();
        }
    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

        register_rest_route('growtype-ai/v1', 'generate/(?P<service>\w+)', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'generate_images_callback'
            ),
            'permission_callback' => function () use ($permission) {
                return $permission;
            }
        ));

        register_rest_route('growtype-ai/v1', 'retrieve/images', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'retrieve_random_images_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        register_rest_route('growtype-ai/v1', 'retrieve/image/(?P<id>\d+)', array (
            'methods' => 'GET',
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

        $image = growtype_ai_get_image_details($image_id);

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
                    $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE, [
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
                    $table = $wpdb->prefix . Growtype_Ai_Database::MODEL_SETTINGS_TABLE;

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

                    $table = $wpdb->prefix . Growtype_Ai_Database::MODEL_IMAGE_TABLE;
                    $query_string = 'SELECT * FROM ' . $table . ' WHERE model_id IN (' . implode(',', $models_ids) . ')';
                    $image_models = $wpdb->get_results("{$query_string}", ARRAY_A);

                    $images_ids = array_pluck($image_models, 'image_id');

                    $images_ids = array_slice(array_shuffle($images_ids), 0, 200);

                    $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE, [
                        [
                            'key' => 'id',
                            'values' => array_unique($images_ids),
                        ]
                    ]);

                    break;
            }

            foreach ($images as $image) {
                $image_details = growtype_ai_get_image_details($image['id']);
                $image_details['url'] = growtype_ai_get_image_url($image['id']);

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
