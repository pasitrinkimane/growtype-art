<?php

/**
 * Handles the wp_ajax_growtype_art_admin_update_model AJAX endpoint.
 */
class Growtype_Art_Admin_Models_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_growtype_art_admin_update_model', [$this, 'update_model_callback']);
    }

    public function update_model_callback(): void
    {
        $_POST = stripslashes_deep($_POST);

        $model_id = $_POST['model_id'];
        $value    = $_POST['value'];
        $name     = $_POST['name'];

        $property_to_update = explode('[', rtrim($name, ']'));

        if (is_array($value)) {
            $value = json_encode($value);
        }

        $update_type = $property_to_update[0] ?? '';
        $update_key  = isset($property_to_update[1])
            ? str_replace('"', '', stripslashes($property_to_update[1]))
            : '';

        if ($update_type === 'settings' && in_array($update_key, ['featured_in', 'tags'])) {
            $sanitized_value = sanitize_textarea_field($value);

            if ($update_key === 'tags') {
                $sanitized_value = str_replace(["\r", "\n"], ',', $sanitized_value);
                $sanitized_value = preg_replace('/\s*,\s*/', ',', trim($sanitized_value, ','));

                if (!empty($sanitized_value)) {
                    $sanitized_value = array_values(array_unique(array_filter(explode(',', $sanitized_value))));
                    $sanitized_value = !empty($sanitized_value) ? json_encode($sanitized_value) : '';
                }
            }

            Growtype_Art_Database_Crud::update_records(
                Growtype_Art_Database::MODEL_SETTINGS_TABLE,
                [['key' => 'model_id', 'values' => [$model_id]]],
                ['reference_key' => 'meta_key', 'update_value' => 'meta_value'],
                [$update_key => $sanitized_value]
            );
        } elseif ($update_type === 'model' && in_array($update_key, ['provider'])) {
            Growtype_Art_Database_Crud::update_record(
                Growtype_Art_Database::MODELS_TABLE,
                ['provider' => sanitize_text_field($value)],
                $model_id
            );
        }

        wp_send_json(['message' => __('Updated', 'growtype')], 200);
    }
}
