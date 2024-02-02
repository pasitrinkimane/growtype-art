<?php

class Growtype_Ai_Api_Model
{
    const TRANSIENT_KEY = 'growtype_ai_api_models';

    public function __construct()
    {
        $this->load_methods();

        add_action('rest_api_init', array (
            $this,
            'register_routes'
        ));

        /**
         * Clear transient
         */
        add_action('growtype_ai_model_update', array ($this, 'growtype_ai_model_delete_transient'));
        add_action('growtype_ai_model_delete', array ($this, 'growtype_ai_model_delete_transient'));
        add_action('growtype_ai_model_image_delete', array ($this, 'growtype_ai_model_delete_transient'));
        add_action('growtype_ai_model_image_update', array ($this, 'growtype_ai_model_delete_transient'));
    }

    function load_methods()
    {
    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

        register_rest_route('growtype-ai/v1', 'retrieve/model/(?P<id>\d+)', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'retrieve_model_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        register_rest_route('growtype-ai/v1', 'retrieve/characters/(?P<featured_in>\w+)', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'retrieve_characters_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        register_rest_route('growtype-ai/v1', 'retrieve/colors', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'retrieve_colors_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));
    }

    function retrieve_characters_callback($data)
    {
        $featured_in_groups = isset($data['featured_in']) ? explode(',', $data['featured_in']) : [];

        foreach ($featured_in_groups as $featured_in_group) {
            $options = growtype_ai_get_model_featured_in_options();

            if (!in_array($featured_in_group, array_keys($options))) {
                return wp_send_json([
                    'data' => 'Invalid featured_in',
                ], 400);
            }
        }

        $response = get_transient(self::TRANSIENT_KEY);

        if (empty($response)) {
            $return_data = growtype_ai_get_featured_in_group_images($featured_in_groups);

            $response = [];
            foreach ($return_data as $return_data_single) {
                if ($return_data_single['cover_images'] < 2 || $return_data_single['featured_images_count'] < 4) {
                    continue;
                }

                array_push($response, $return_data_single);
            }

            set_transient(self::TRANSIENT_KEY, $response, 60 * 60 * 24);
        }

        return wp_send_json($response, 200);
    }

    function retrieve_model_callback($data)
    {
        $model_id = isset($data['id']) ? $data['id'] : null;

        if (empty($model_id)) {
            return;
        }

        $return_data = [];

        $model = growtype_ai_get_model_details($model_id);
        $images = growtype_ai_get_model_images($model_id);

        $return_data['prompt'] = $model['prompt'];

        foreach ($images as $image) {
            $return_data['images'][] = [
//                'id' => $image['id'],
                'url' => growtype_ai_get_image_url($image['id']),
                'categories' => isset($model['settings']['categories']) ? $model['settings']['categories'] : [],
            ];
        };

        return wp_send_json([
            'data' => $return_data,
        ], 200);
    }

    function retrieve_colors_callback($data)
    {
        $return_data = growtype_ai_colors_groups();

        return wp_send_json([
            'colors' => $return_data,
        ], 200);
    }

    function growtype_ai_model_delete_transient()
    {
        delete_transient(self::TRANSIENT_KEY);
    }
}
