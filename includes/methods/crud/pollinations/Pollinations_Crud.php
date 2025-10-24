<?php

class Pollinations_Crud
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
        include GROWTYPE_ART_PATH . '/includes/methods/crud/pollinations/partials/Pollinations_Base.php';
    }
}

