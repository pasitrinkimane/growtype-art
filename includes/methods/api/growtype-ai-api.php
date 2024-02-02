<?php

class Growtype_Ai_Api
{
    public function __construct()
    {
        $this->load_methods();
    }

    function load_methods()
    {
        /**
         *
         */
        include_once 'partials/Growtype_Ai_Api_Model.php';
        new Growtype_Ai_Api_Model();

        /**
         *
         */
        include_once 'partials/Growtype_Ai_Api_Meal.php';
        new Growtype_Ai_Api_Meal();

        /**
         *
         */
        include_once 'partials/Growtype_Ai_Api_Character.php';
        new Growtype_Ai_Api_Character();

        /**
         *
         */
        include_once 'partials/Growtype_Ai_Api_Color.php';
        new Growtype_Ai_Api_Color();
    }
}
