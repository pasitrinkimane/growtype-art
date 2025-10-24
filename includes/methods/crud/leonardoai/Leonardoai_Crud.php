<?php

class Leonardoai_Crud
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
        include GROWTYPE_ART_PATH . '/includes/methods/crud/leonardoai/partials/Leonardoai_Base.php';

        /**
         * Cpt
         */
        include GROWTYPE_ART_PATH . '/includes/methods/crud/leonardoai/partials/Leonardoai_Meta.php';
        new Leonardoai_Meta();

        /**
         * Feed
         */
        include GROWTYPE_ART_PATH . '/includes/methods/crud/leonardoai/partials/Leonardoai_Feed.php';
        new Leonardoai_Feed();
    }
}

