<?php

class Promptchan_Crud
{
    public function __construct()
    {
        $this->load_methods();
        add_filter('growtype_auth_admin_settings_credentials_available_services', [$this, 'register_credentials_service']);
    }

    private function load_methods()
    {
        include_once GROWTYPE_ART_PATH . '/includes/methods/crud/promptchan/partials/Promptchan_Base.php';
    }

    public function register_credentials_service(array $services): array
    {
        $services[Growtype_Art_Crud::PROMPTCHAN_KEY] = [
            'description' => "Create an API key in Promptchan and paste it below.",
            'fields' => [
                [
                    'name' => 'api_key',
                    'label' => 'API Key',
                    'placeholder' => 'Promptchan API key',
                    'type' => 'password',
                    'default' => '',
                ],
            ],
        ];

        return $services;
    }
}
