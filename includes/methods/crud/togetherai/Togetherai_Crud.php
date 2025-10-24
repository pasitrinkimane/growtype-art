<?php

class Togetherai_Crud
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
        include GROWTYPE_ART_PATH . '/includes/methods/crud/togetherai/partials/Togetherai_Base.php';
    }
}

