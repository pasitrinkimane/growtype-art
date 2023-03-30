<?php

class Growtype_Ai_Database
{
    const MODELS_TABLE = 'growtype_ai_models';
    const MODEL_SETTINGS_TABLE = 'growtype_ai_model_settings';
    const IMAGES_TABLE = 'growtype_ai_images';
    const MODEL_IMAGE_TABLE = 'growtype_ai_model_image';
    const MODEL_JOBS_TABLE = 'growtype_ai_jobs';
    const IMAGE_SETTINGS_TABLE = 'growtype_ai_image_settings';

    const REBUILD_TABLE = false; // IMPORTANT: Set to true to rebuild the database tables

    public function __construct()
    {
        add_action('init', array ($this, 'create_tables'), 5);

        $this->load_methods();
    }

    public static function get_tables()
    {
        global $wpdb;

        return [
            [
                'name' => $wpdb->prefix . self::MODELS_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'prompt',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'negative_prompt',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'reference_id',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'image_folder',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'provider',
                        'data_type' => 'TEXT DEFAULT NULL',
                    )
                ]
            ],
            [
                'name' => $wpdb->prefix . self::MODEL_SETTINGS_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'model_id',
                        'data_type' => 'bigint unsigned',
                    ),
                    array (
                        'data_field' => 'meta_key',
                        'data_type' => 'VARCHAR(255)',
                    ),
                    array (
                        'data_field' => 'meta_value',
                        'data_type' => 'longtext',
                    )
                ]
            ],
            [
                'name' => $wpdb->prefix . self::IMAGES_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'name',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'extension',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'width',
                        'data_type' => 'VARCHAR(255) DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'height',
                        'data_type' => 'VARCHAR(255) DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'location',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'folder',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'reference_id',
                        'data_type' => 'TEXT DEFAULT NULL',
                    )
                ]
            ],
            [
                'name' => $wpdb->prefix . self::IMAGE_SETTINGS_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'image_id',
                        'data_type' => 'bigint unsigned',
                    ),
                    array (
                        'data_field' => 'meta_key',
                        'data_type' => 'VARCHAR(255)',
                    ),
                    array (
                        'data_field' => 'meta_value',
                        'data_type' => 'longtext',
                    )
                ]
            ],
            [
                'name' => $wpdb->prefix . self::MODEL_IMAGE_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'model_id',
                        'data_type' => 'INTEGER',
                    ),
                    array (
                        'data_field' => 'image_id',
                        'data_type' => 'INTEGER',
                    )
                ]
            ],
            [
                'name' => $wpdb->prefix . self::MODEL_JOBS_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'queue',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'payload',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'exception',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'attempts',
                        'data_type' => 'INTEGER',
                    ),
                    array (
                        'data_field' => 'reserved',
                        'data_type' => 'INTEGER',
                    ),
                    array (
                        'data_field' => 'reserved_at',
                        'data_type' => 'DATETIME',
                    ),
                    array (
                        'data_field' => 'available_at',
                        'data_type' => 'DATETIME',
                    )
                ]
            ]
        ];
    }

    // genre, style, composition, color scheme, subject, mood, technique.

    /**
     * Create required table
     */
    public function create_tables()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tables = $this->get_tables();

        foreach ($tables as $table) {
            $table_name = $table['name'];

            if (self::REBUILD_TABLE) {
                $wpdb->query("DROP TABLE IF EXISTS $table_name");
            }

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,";
                foreach ($table['fields'] as $field) {
                    $sql .= $field['data_field'] . ' ' . $field['data_type'] . ', ';
                }
                $sql .= "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY id (id)
    ) $charset_collate;";

                dbDelta($sql);
            }
        }
    }

    public function load_methods()
    {
        require_once GROWTYPE_AI_PATH . 'database/methods/class-growtype-ai-database-crud.php';

        require_once GROWTYPE_AI_PATH . 'database/methods/class-growtype-ai-database-optimize.php';
    }
}

new Growtype_Ai_Database();
