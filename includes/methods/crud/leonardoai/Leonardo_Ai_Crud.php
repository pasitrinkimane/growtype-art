<?php

class Openai_Crud
{
    public function __construct()
    {
        $this->load_methods();
    }

    private function load_methods()
    {
        /**
         * Base
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/openai/partials/Openai_Base.php';

        /**
         * Image
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/openai/partials/Openai_Base_Image.php';
        new Openai_Base_Image();

        /**
         * Image
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/openai/partials/Openai_Base_Meal.php';
        new Openai_Base_Meal();
    }
}

