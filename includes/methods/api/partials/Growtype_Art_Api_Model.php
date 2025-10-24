<?php

class Growtype_Art_Api_Model
{
    public function __construct()
    {
        $this->load_methods();

        add_action('rest_api_init', array (
            $this,
            'register_routes'
        ));
    }

    function load_methods()
    {
    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

        register_rest_route('growtype-art/v1', 'retrieve/model/(?P<id>\d+)', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array (
                $this,
                'retrieve_model_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));
    }

    function retrieve_model_callback($data)
    {
        $model_id = isset($data['id']) ? $data['id'] : null;

        if (empty($model_id)) {
            return;
        }

        $return_data = [];

        $model = growtype_art_get_model_details($model_id);

        $images = growtype_art_get_model_images_grouped($model_id)['original'] ?? [];

        $return_data['prompt'] = $model['prompt'];

        foreach ($images as $image) {
            $return_data['images'][] = [
//                'id' => $image['id'],
                'url' => growtype_art_get_image_url($image['id']),
                'categories' => isset($model['settings']['categories']) ? $model['settings']['categories'] : [],
            ];
        };

        return wp_send_json([
            'data' => $return_data,
        ], 200);
    }
}
