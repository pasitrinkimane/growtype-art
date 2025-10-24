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
        include GROWTYPE_ART_PATH . '/includes/methods/crud/openai/partials/Openai_Base.php';

        /**
         * Image
         */
        include GROWTYPE_ART_PATH . '/includes/methods/crud/openai/partials/Openai_Base_Image.php';
        new Openai_Base_Image();

        /**
         * Meal
         */
        include GROWTYPE_ART_PATH . '/includes/methods/crud/openai/partials/Openai_Base_Meal.php';
        new Openai_Base_Meal();

        /**
         * Image
         */
        include GROWTYPE_ART_PATH . '/includes/methods/crud/openai/partials/Openai_Base_Character.php';
        new Openai_Base_Character();
    }
}

