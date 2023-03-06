<?php

class Growtype_Ai_Crud
{
    public function __construct()
    {
        $this->load_methods();
    }

    public function load_methods()
    {
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/leonardoai/Leonardo_Ai_Crud.php';
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/cloudinary/Cloudinary_Crud.php';
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/openai/Openai_Crud.php';
    }
}
