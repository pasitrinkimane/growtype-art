<?php

/**
 * Class Growtype_Art_In_Gallery
 */
class Growtype_Art_Shortcode
{
    public function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_art', array ($this, 'growtype_art_shortcode'));
        }
    }

    /**
     *
     */
    function growtype_art_shortcode($atts)
    {

    }
}
