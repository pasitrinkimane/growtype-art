<?php

class Growtype_Art_Admin_Models
{
    public function __construct()
    {
        $this->load_partials();
    }

    private function load_partials(): void
    {
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/filters/class-growtype-art-admin-models-filters.php';
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/view/class-growtype-art-admin-models-images-view.php';
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/ajax/class-growtype-art-admin-models-ajax.php';
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/page/class-growtype-art-admin-models-page.php';
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/table/growtype-art-admin-model-list-table.php';
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/table/growtype-art-admin-model-list-table-record.php';
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/partials/table/growtype-art-admin-model-generator.php';

        new Growtype_Art_Admin_Models_Images_View();
        new Growtype_Art_Admin_Models_Ajax();
        new Growtype_Art_Admin_Models_Page();
    }
}
