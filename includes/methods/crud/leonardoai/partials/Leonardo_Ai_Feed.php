<?php

use partials\Leonardo_Ai_Base;

class Leonardo_Ai_Feed extends Leonardo_Ai_Base
{
    function get_user_feed($user_nr, $args)
    {
        $token = $this->retrieve_access_token($user_nr);

        return $this->get_feed_images($token, $args);
    }
}


