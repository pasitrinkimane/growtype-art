<?php

class Growtype_Ai_Api
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
        /**
         * Leonardo Ai
         */
        $this->leonardo_ai_crud = new Leonardo_Ai_Crud();
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

        register_rest_route('growtype-ai/v1', 'retrieve/(?P<service>\w+)/(?P<amount>\d+)', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'retrieve_images_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return $permission;
            }
        ));

        // generate
        //retrieve
        // delete
    }

    function generate_images_callback($data)
    {
        $service = isset($data['service']) ? $data['service'] : null;

        if ($service === 'leonardoai') {
            $this->leonardo_ai_crud->generate_model();
        }
    }

    function retrieve_images_callback($data)
    {
        $service = isset($data['service']) ? $data['service'] : null;
        $amount = isset($data['amount']) ? $data['amount'] : 1;

        $images = null;

        if ($service === 'leonardoai') {
//            $images = $this->leonardo_ai_crud->retrieve_models($amount);
        }

        return wp_send_json([
            'data' => $images,
        ], 200);
    }
}
