<?php

class Growtype_Art_Api_Color
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

        register_rest_route('growtype-art/v1', 'retrieve/colors', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array (
                $this,
                'retrieve_colors_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));
    }

    function retrieve_colors_callback($data)
    {
        $return_data = growtype_art_colors_groups();

        return wp_send_json([
            'colors' => $return_data,
        ], 200);
    }
}
