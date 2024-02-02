<?php

class Leonardo_Ai_Crud
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
        include GROWTYPE_AI_PATH . '/includes/methods/crud/leonardoai/partials/Leonardo_Ai_Base.php';

        /**
         * Cpt
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/leonardoai/partials/Leonardo_Ai_Meta.php';
        new Leonardo_Ai_Meta();

        /**
         * Feed
         */
        include GROWTYPE_AI_PATH . '/includes/methods/crud/leonardoai/partials/Leonardo_Ai_Feed.php';
        new Leonardo_Ai_Feed();
    }
}

