<?php

class Piclumen_Crud
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
        include GROWTYPE_ART_PATH . '/includes/methods/crud/piclumen/partials/Piclumen_Base.php';
    }
}

