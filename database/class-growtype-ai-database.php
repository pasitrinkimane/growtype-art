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
    const CLEAN_DUPLICATED_RECORDS = false; // IMPORTANT: Clean duplicated records

    public function __construct()
    {
        add_action('init', array ($this, 'create_tables'), 5);
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
                        'data_field' => 'image_location',
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

            if ($table_name === 'wp_growtype_ai_model_settings' && self::CLEAN_DUPLICATED_RECORDS) {
                $records = $wpdb->get_results("SELECT * FROM wp_growtype_ai_model_settings", ARRAY_A);

                $filtered_records = [];
                foreach ($records as $record) {
                    if (!isset($filtered_records[$record['model_id']][$record['meta_key']])) {
                        $filtered_records[$record['model_id']][$record['meta_key']] = $record;
                    } else {
                        $wpdb->delete('wp_growtype_ai_model_settings', array ('id' => $record['id']));
                    }
                }

                $records = $wpdb->get_results("SELECT * FROM wp_growtype_ai_image_settings", ARRAY_A);

                $filtered_records = [];
                foreach ($records as $record) {
                    if (!isset($filtered_records[$record['image_id']][$record['meta_key']])) {
                        $filtered_records[$record['image_id']][$record['meta_key']] = $record;
                    } else {
                        $wpdb->delete('wp_growtype_ai_image_settings', array ('id' => $record['id']));
                    }
                }
            }
        }
    }

    public static function get_single_record($table, $params)
    {
        return !empty(self::get_records($table, $params)) ? self::get_records($table, $params)[0] : null;
    }

    public static function get_records($table, $params = null, $condition = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        if (empty($params)) {
            return $wpdb->get_results("SELECT * FROM " . $table, ARRAY_A);
        }

        $records = [];

        if (!empty($condition) && $condition === 'where') {
            $query_where = [];

            foreach ($params as $param) {
                array_push($query_where, $param['key'] . "='" . $param['value'] . "'");
            }

            $query = "SELECT * FROM " . $table . " where " . implode(' AND ', $query_where);

            $records = $wpdb->get_results($query, ARRAY_A);
        } else {
            foreach ($params as $param) {
                $limit = isset($param['limit']) ? $param['limit'] : 1000;
                $offset = isset($param['offset']) ? $param['offset'] : 0;
                $search = isset($param['search']) ? $param['search'] : null;
                $orderby = isset($param['orderby']) ? $param['orderby'] : 'created_at';
                $order = isset($param['order']) ? $param['order'] : 'desc';
                $values = isset($param['values']) ? $param['values'] : null;
                $key = isset($param['key']) ? $param['key'] : null;

                if (!empty($values) && !empty($key)) {
                    $placeholders = implode(', ', array_fill(0, count($values), '%s'));
                    $query = "SELECT * FROM " . $table . " WHERE " . $key . " IN($placeholders)";
                    $query = $wpdb->prepare($query, $values);
                } elseif (!empty($search)) {
                    $query = "SELECT * from {$table} WHERE id Like '%{$search}%' OR prompt Like '%{$search}%' OR negative_prompt Like '%{$search}%' OR reference_id Like '%{$search}%' ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
                } else {
                    $query = "SELECT * from {$table} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
                }

                $results = $wpdb->get_results($query, ARRAY_A);

                $records = array_merge($records, $results);
            }
        }

        return $records;
    }

    public static function get_pivot_records($pivot_table, $records_table, $source, $params = null)
    {
        global $wpdb;

        $records = self::get_records($pivot_table, $params);

        if (empty($records)) {
            return [];
        }

        $ids = array_pluck($records, $source);

        return self::get_records($records_table, [
            [
                'key' => 'id',
                'values' => $ids,
            ]
        ]);
    }

    public static function insert_record($table, $data)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    public static function update_record($table, $data, $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        $wpdb->update($table, $data, array ('id' => $id));
    }

    public static function update_records($table, $retrieve_data, $record_params, $update_data)
    {
        $records = Growtype_Ai_Database::get_records($table, $retrieve_data);

        foreach ($records as $record) {
            $record_key = $record[$record_params['reference_key']];
            if (isset($update_data[$record_key])) {
                $update_value = $update_data[$record_key];
                self::update_record($table, [$record_params['update_value'] => $update_value], $record['id']);
            }
        }

        foreach ($update_data as $key => $value) {
            if (!in_array($key, array_pluck($records, $record_params['reference_key']))) {
                self::insert_record($table, [
                    $retrieve_data[0]['key'] => $retrieve_data[0]['values'][0],
                    $record_params['reference_key'] => $key,
                    $record_params['update_value'] => $value
                ]);
            }
        }
    }

    public static function delete_records($table_name, $ids)
    {
        global $wpdb;

        if (empty($ids)) {
            return;
        }

        $table = $wpdb->prefix . $table_name;

        if ($table_name === self::MODELS_TABLE) {
            $settings = self::get_records(self::MODEL_SETTINGS_TABLE, [
                [
                    'key' => 'model_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, array_pluck($settings, 'id'));

            $model_image = self::get_records(self::MODEL_IMAGE_TABLE, [
                [
                    'key' => 'model_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Ai_Database::IMAGES_TABLE, array_pluck($model_image, 'image_id'));

            self::delete_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, array_pluck($model_image, 'id'));
        } elseif ($table_name === self::IMAGES_TABLE) {
            $model_image = self::get_records(self::MODEL_IMAGE_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, array_pluck($model_image, 'id'));
        } elseif ($table_name === self::IMAGE_SETTINGS_TABLE) {
            $image_setting = self::get_records(self::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, array_pluck($image_setting, 'id'));
        }

        $ids = implode(',', array_map('absint', $ids));

        $wpdb->query("DELETE FROM " . $table . " WHERE ID IN($ids)");
    }

    public static function delete_single_record($table_name, $params)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table_name;

        $query_where = [];
        foreach ($params as $param) {
            array_push($query_where, $param['key'] . "='" . $param['value'] . "'");
        }

        $query_where = "DELETE FROM " . $table . " where " . implode(' AND ', $query_where);

        $wpdb->query($query_where);
    }
}

new Growtype_Ai_Database();
