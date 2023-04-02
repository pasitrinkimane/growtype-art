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
//        $this->leonardo_ai_crud = new Leonardo_Ai_Crud();
    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

//        register_rest_route('growtype-ai/v1', 'generate/(?P<service>\w+)', array (
//            'methods' => 'GET',
//            'callback' => array (
//                $this,
//                'generate_images_callback'
//            ),
//            'permission_callback' => function () use ($permission) {
//                return $permission;
//            }
//        ));

        register_rest_route('growtype-ai/v1', 'retrieve/(?P<service>\w+)/(?P<model>\d+)', array (
            'methods' => 'GET',
            'callback' => array (
                $this,
                'retrieve_images_callback'
            ),
//            'permission_callback' => function ($user) use ($permission) {
//                return $permission;
//            }
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
        $model_id = isset($data['model']) ? $data['model'] : null;

        if (empty($model_id)) {
            return;
        }

        $images = growtype_ai_get_model_images($model_id);

        $return_data = [];
        foreach ($images as $image) {

            $image['url'] = growtype_ai_get_image_url($image['id']);

            array_push($return_data, $image);
        };

        return wp_send_json([
            'data' => $return_data,
        ], 200);
    }
}
