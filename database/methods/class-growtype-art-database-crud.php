<?php

class Growtype_Art_Database_Crud
{
    public static function table_total_records_amount($table_name)
    {
        global $wpdb;
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        return $total_records;
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
                $limit = isset($param['limit']) ? $param['limit'] : 15;
                $offset = isset($param['offset']) ? $param['offset'] : 0;
                $search = isset($param['search']) ? $param['search'] : null;
                $orderby = isset($param['orderby']) ? $param['orderby'] : 'created_at';
                $order = isset($param['order']) ? $param['order'] : 'desc';
                $values = isset($param['values']) ? $param['values'] : null;
                $key = isset($param['key']) ? $param['key'] : null;

                if (!empty($values) && !empty($key)) {
                    $placeholders = implode(', ', array_fill(0, count($values), '%s'));
                    $query = "SELECT * FROM " . $table . " WHERE " . $key . " IN($placeholders) ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

                    $query = $wpdb->prepare($query, $values);
                } elseif (!empty($search)) {
                    switch ($table) {
                        case 'wp_growtype_art_models':
                            $query = "SELECT aimo.id AS id, 
       aimo.prompt AS prompt, 
       aimo.negative_prompt AS negative_prompt, 
       aimo.reference_id AS reference_id, 
       aimo.created_at AS created_at
FROM {$table} AS aimo 
LEFT JOIN wp_growtype_art_model_settings AS aims ON (aimo.id = aims.model_id AND aims.meta_key='created_by_unique_hash') 
LEFT JOIN wp_growtype_art_model_settings AS aims2 ON (aimo.id = aims2.model_id AND aims2.meta_key='created_by')
LEFT JOIN wp_growtype_art_model_settings AS aims3 ON (aimo.id = aims3.model_id AND aims3.meta_key='character_title')
WHERE aimo.id LIKE '%{$search}%' 
OR aimo.prompt LIKE '%{$search}%' 
OR aimo.negative_prompt LIKE '%{$search}%' 
OR aimo.reference_id LIKE '%{$search}%'
OR aims.meta_value LIKE '%{$search}%'
OR aims2.meta_value LIKE '%{$search}%'
OR aims3.meta_value LIKE '%{$search}%'
GROUP BY aimo.id 
ORDER BY {$orderby} {$order} 
LIMIT {$limit} OFFSET {$offset}";
                            break;
                        default:
                            $query = "SELECT * from {$table} WHERE id Like '%{$search}%' OR prompt Like '%{$search}%' OR negative_prompt Like '%{$search}%' OR reference_id Like '%{$search}%' ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
                    }
                } else {
                    $query = "SELECT * from {$table} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
                }

//                var_dump($query);

                $results = $wpdb->get_results($query, ARRAY_A);

                $records = array_merge($records, $results);
            }
        }

        return $records;
    }

    public static function get_pivot_records($pivot_table, $records_table, $source, $params = null)
    {
        global $wpdb;

        // Sanitize and prefix table names
        $pivot_table = esc_sql($wpdb->prefix . $pivot_table);
        $records_table = esc_sql($wpdb->prefix . $records_table);
        $source = esc_sql($source);

        // Build WHERE clause
        $where_clauses = '';
        $values = [];
        $limit = '';
        $offset = '';

        if (!empty($params) && is_array($params)) {
            foreach ($params as $condition) {
                if (isset($condition['key'], $condition['values']) && is_array($condition['values']) && count($condition['values']) > 0) {
                    $key = esc_sql($condition['key']);
                    $placeholders = implode(',', array_fill(0, count($condition['values']), '%s'));
                    $where_clauses .= " AND p.{$key} IN ($placeholders)";
                    $values = array_merge($values, $condition['values']);
                }

                // Handle limit and offset
                if (isset($condition['limit']) && is_numeric($condition['limit'])) {
                    $limit = intval($condition['limit']);
                }
                if (isset($condition['offset']) && is_numeric($condition['offset'])) {
                    $offset = intval($condition['offset']);
                }
            }
        }

        // Final SQL query
        $sql = "
        SELECT r.*
        FROM {$pivot_table} AS p
        INNER JOIN {$records_table} AS r ON r.id = p.{$source}
        WHERE 1=1
        {$where_clauses}
    ";

        // Add LIMIT/OFFSET if needed
        if ($limit !== '') {
            $sql .= " LIMIT %d";
            $values[] = $limit;

            if ($offset !== '') {
                $sql .= " OFFSET %d";
                $values[] = $offset;
            }
        }

        // Prepare and execute
        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    public static function insert_record($table, $data)
    {
        global $wpdb;

        if (empty($data)) {
            return;
        }

        $table = $wpdb->prefix . $table;

        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    public static function update_record($table, $data, $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        $data['updated_at'] = current_time('mysql');

        $wpdb->update($table, $data, array ('id' => $id));
    }

    public static function update_records($table, $retrieve_data, $record_params, $update_data)
    {
        $records = self::get_records($table, $retrieve_data);

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

        if ($table_name === Growtype_Art_Database::MODELS_TABLE) {
            $settings = self::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                [
                    'key' => 'model_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, array_pluck($settings, 'id'));

            $model_image = self::get_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                [
                    'key' => 'model_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Art_Database::IMAGES_TABLE, array_pluck($model_image, 'image_id'));
        } elseif ($table_name === Growtype_Art_Database::IMAGES_TABLE) {
            $image_settings = self::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            if (!empty($image_settings)) {
                self::delete_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, array_pluck($image_settings, 'id'));

                foreach ($image_settings as $image_setting) {
                    if ($image_setting['meta_key'] === 'parent_image_id') {
                        $parent_image_settings = self::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                            [
                                'key' => 'image_id',
                                'values' => [$image_setting['meta_value']],
                            ]
                        ]);

                        foreach ($parent_image_settings as $parent_image_setting) {
                            foreach ($ids as $id) {
                                if ($parent_image_setting['meta_key'] === 'video_url_image_id_' . $id) {
                                    self::delete_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [$parent_image_setting['id']]);
                                }
                            }
                        }
                    }
                }
            }

            $model_image = self::get_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, array_pluck($model_image, 'id'));
        } elseif ($table_name === Growtype_Art_Database::IMAGE_SETTINGS_TABLE) {
            $image_setting = self::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            self::delete_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, array_pluck($image_setting, 'id'));
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

    public static function custom_query($sql, $params = [])
    {
        global $wpdb;

        // Prepare the SQL with placeholders
        $prepared_sql = $wpdb->prepare($sql, ...$params);

        // Execute and return results
        return $wpdb->get_results($prepared_sql, ARRAY_A);
    }
}
