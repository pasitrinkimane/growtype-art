<?php

class Perchance_Crud
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
        include GROWTYPE_ART_PATH . '/includes/methods/crud/perchance/partials/Perchance_Base.php';
    }
}

