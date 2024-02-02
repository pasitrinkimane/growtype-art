<?php

class Openai_Loader
{

    public function __construct()
    {
        $this->load_methods();
    }

    private function load_methods()
    {
        /**
         * Image
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/openai/Openai_Crud.php';
        new Openai_Crud();

        /**
         * Image
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/openai/partials/image.php';
        new Openai_Crud_Image();

        /**
         * Image
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/openai/partials/meals.php';
        new Openai_Crud_Meals();
    }
}

