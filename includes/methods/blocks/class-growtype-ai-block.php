<?php

/**
 *
 */
class Growtype_Ai_Block
{
    function __construct()
    {
        add_action('init', array ($this, 'create_block_growtype_ai_block_init'));

        add_action('rest_api_init', array (
            $this,
            'register_custom_rest_route',
        ));
    }

    function register_custom_rest_route()
    {
        register_rest_route('growtype-ai/v1', '/settings/', array (
            'methods' => 'GET',
            'callback' => function () {
                return [
                    'available_post_types' => $this->get_available_post_types(),
                ];
            },
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * @return void
     */
    function create_block_growtype_ai_block_init()
    {
        register_block_type_from_metadata(GROWTYPE_AI_PATH . 'build', [
            'render_callback' => array ($this, 'render_callback_growtype_ai'),
        ]);
    }

    /**
     * @param $block_attributes
     * @param $content
     * @return mixed
     */
    function render_callback_growtype_ai($block_attributes, $content)
    {
        return do_shortcode($content);
    }

    /**
     * @return array
     */
    function get_available_post_types()
    {
        return Growtype_Ai_Customizer::get_available_post_types();
    }
}
