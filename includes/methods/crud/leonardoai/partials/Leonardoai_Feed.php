<?php

use partials\Leonardoai_Base;

class Leonardoai_Feed extends Leonardoai_Base
{
    function get_user_feed($user_nr, $args)
    {
        $token = $this->retrieve_access_token($user_nr);

        return $this->get_feed_images($token, $args);
    }
}


