<?php

/**
 * Class Growtype_Ai_In_Gallery
 */
class Growtype_Ai_Shortcode
{
    public function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_ai', array ($this, 'growtype_ai_shortcode'));
        }
    }

    /**
     *
     */
    function growtype_ai_shortcode($atts)
    {

    }
}
