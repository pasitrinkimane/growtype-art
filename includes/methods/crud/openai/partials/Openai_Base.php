<?php

class Openai_Base
{
    public function __construct()
    {
        $this->open_ai_key = self::api_key();
    }

    public static function api_key()
    {
        return get_option('growtype_ai_openai_api_key');
    }
}

