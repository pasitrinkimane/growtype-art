<?php

class Growtype_Art_Database_Crud
{
    public static function table_total_records_amount($table_name)
    {
        global $wpdb;
        $table_name = esc_sql($table_name);
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        return $total_records;
    }

    public static function get_single_record($table, $params)
    {
        $records = self::get_records($table, $params);
        return !empty($records) ? $records[0] : null;
    }

    public static function count_records($table, $params = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . $table;

        if (empty($params)) {
            return (int)$wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($table));
        }

        $count = 0;
        foreach ($params as $param) {
            $search = isset($param['search']) ? $param['search'] : null;
            $values = isset($param['values']) ? $param['values'] : null;
            $key = isset($param['key']) ? esc_sql($param['key']) : null;

            if (!empty($values) && !empty($key)) {
                $placeholders = implode(', ', array_fill(0, count($values), '%s'));
                $query = $wpdb->prepare("SELECT COUNT(*) FROM " . esc_sql($table) . " WHERE " . $key . " IN($placeholders)", ...$values);
                $count += (int)$wpdb->get_var($query);
            } elseif (!empty($search) && is_scalar($search)) {
                $search_like = '%' . $wpdb->esc_like((string)$search) . '%';
                if ($table === $wpdb->prefix . 'growtype_art_models') {
                    $query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT aimo.id)
                        FROM " . esc_sql($table) . " AS aimo
                        LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims ON (aimo.id = aims.model_id AND aims.meta_key='created_by_unique_hash')
                        LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims2 ON (aimo.id = aims2.model_id AND aims2.meta_key='created_by')
                        LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims3 ON (aimo.id = aims3.model_id AND aims3.meta_key='character_title')
                        LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims4 ON (aimo.id = aims4.model_id AND aims4.meta_key='slug')
                        WHERE aimo.id LIKE %s OR aimo.prompt LIKE %s OR aimo.negative_prompt LIKE %s OR aimo.reference_id LIKE %s OR aims.meta_value LIKE %s OR aims2.meta_value LIKE %s OR aims3.meta_value LIKE %s OR aims4.meta_value LIKE %s",
                        $search_like, $search_like, $search_like, $search_like, $search_like, $search_like, $search_like, $search_like);
                    $count += (int)$wpdb->get_var($query);
                } else {
                    $query = $wpdb->prepare("SELECT COUNT(*) FROM " . esc_sql($table) . " WHERE id LIKE %s OR prompt LIKE %s", $search_like, $search_like);
                    $count += (int)$wpdb->get_var($query);
                }
            } else {
                $count += (int)$wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($table));
            }
        }

        return $count;
    }

    public static function get_records($table, $params = null, $condition = null)
    {
        global $wpdb;

        if (empty($table)) {
            return [];
        }

        $table = $wpdb->prefix . $table;

        if (empty($params)) {
            return $wpdb->get_results("SELECT * FROM " . esc_sql($table) . " LIMIT 1000", ARRAY_A);
        }

        $records = [];

        if (!empty($condition) && $condition === 'where') {
            $query_where = [];
            $values = [];

            foreach ($params as $param) {
                if (!isset($param['key']) || !isset($param['value'])) {
                    continue;
                }
                $query_where[] = esc_sql($param['key']) . " = %s";
                $values[] = is_array($param['value']) ? json_encode($param['value']) : ($param['value'] ?? '');
            }

            if (empty($query_where)) {
                return [];
            }

            $query = "SELECT * FROM " . esc_sql($table) . " WHERE " . implode(' AND ', $query_where) . " LIMIT 1000";
            $prepared_query = $wpdb->prepare($query, $values);

            if ($prepared_query) {
                $records = $wpdb->get_results($prepared_query, ARRAY_A);
            }
        } else {
            foreach ($params as $param) {
                $limit = isset($param['limit']) ? (int)$param['limit'] : 1000;
                $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
                $search = isset($param['search']) ? $param['search'] : null;
                $orderby = isset($param['orderby']) ? esc_sql($param['orderby']) : 'created_at';
                $order = isset($param['order']) && strtoupper($param['order']) === 'ASC' ? 'ASC' : 'DESC';
                $values = isset($param['values']) ? $param['values'] : null;
                $key = isset($param['key']) ? esc_sql($param['key']) : null;

                $query = '';
                if (!empty($values) && !empty($key)) {
                    $placeholders = implode(', ', array_fill(0, count($values), '%s'));
                    $query_raw = "SELECT * FROM " . esc_sql($table) . " WHERE " . $key . " IN($placeholders) ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
                    
                    // Sanitize values
                    $sanitized_values = array_map(function($v) {
                        return is_array($v) ? json_encode($v) : ($v ?? '');
                    }, (array)$values);
                    $prepare_values = array_merge($sanitized_values, [$limit, $offset]);
                    $query = $wpdb->prepare($query_raw, $prepare_values);
                } elseif (!empty($search) && is_scalar($search)) {
                    $search_like = '%' . $wpdb->esc_like((string)$search) . '%';

                    switch ($table) {
                        case $wpdb->prefix . 'growtype_art_models':
                            $query_raw = "SELECT aimo.id AS id,
                                    aimo.prompt AS prompt,
                                    aimo.negative_prompt AS negative_prompt,
                                    aimo.reference_id AS reference_id,
                                    aimo.created_at AS created_at
                                FROM " . esc_sql($table) . " AS aimo
                                LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims ON (aimo.id = aims.model_id AND aims.meta_key='created_by_unique_hash')
                                LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims2 ON (aimo.id = aims2.model_id AND aims2.meta_key='created_by')
                                LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims3 ON (aimo.id = aims3.model_id AND aims3.meta_key='character_title')
                                LEFT JOIN " . esc_sql($wpdb->prefix . 'growtype_art_model_settings') . " AS aims4 ON (aimo.id = aims4.model_id AND aims4.meta_key='slug')
                                WHERE aimo.id LIKE %s
                                    OR aimo.prompt LIKE %s
                                    OR aimo.negative_prompt LIKE %s
                                    OR aimo.reference_id LIKE %s
                                    OR aims.meta_value LIKE %s
                                    OR aims2.meta_value LIKE %s
                                    OR aims3.meta_value LIKE %s
                                    OR aims4.meta_value LIKE %s
                                GROUP BY aimo.id
                                ORDER BY {$orderby} {$order}
                                LIMIT %d OFFSET %d";
                                
                            $query = $wpdb->prepare($query_raw, 
                                $search_like, $search_like, $search_like, $search_like,
                                $search_like, $search_like, $search_like, $search_like,
                                $limit, $offset
                            );
                            break;
                        default:
                            $query_raw = "SELECT * FROM " . esc_sql($table) . " WHERE id LIKE %s OR prompt LIKE %s OR negative_prompt LIKE %s OR reference_id LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
                            $query = $wpdb->prepare($query_raw, 
                                $search_like, $search_like, $search_like, $search_like,
                                $limit, $offset
                            );
                    }
                } else {
                    $query_raw = "SELECT * FROM " . esc_sql($table) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
                    $query = $wpdb->prepare($query_raw, $limit, $offset);
                }

                if ($query) {
                    $results = $wpdb->get_results($query, ARRAY_A);
                    if (!empty($results)) {
                        $records = array_merge($records, $results);
                    }
                } else {
                    error_log('Growtype Art - DEBUG: $query is null in get_records. Raw: ' . ($query_raw ?? 'NULL') . ' Params: ' . json_encode($param) . ' Backtrace: ' . wp_debug_backtrace_summary());
                }
            }
        }

        return $records;
    }

    public static function get_pivot_records($pivot_table, $records_table, $source, $params = null)
    {
        global $wpdb;

        $pivot_table = esc_sql($wpdb->prefix . $pivot_table);
        $records_table = esc_sql($wpdb->prefix . $records_table);
        $source = esc_sql($source);

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
                    $sanitized_vals = array_map(function($v) { return $v ?? ''; }, $condition['values']);
                    $values = array_merge($values, $sanitized_vals);
                }

                if (isset($condition['limit']) && is_numeric($condition['limit'])) {
                    $limit = intval($condition['limit']);
                }
                if (isset($condition['offset']) && is_numeric($condition['offset'])) {
                    $offset = intval($condition['offset']);
                }
            }
        }

        $sql = "
            SELECT r.*
            FROM {$pivot_table} AS p
            INNER JOIN {$records_table} AS r ON r.id = p.{$source}
            WHERE 1=1
            {$where_clauses}
        ";

        if ($limit !== '') {
            $sql .= " LIMIT %d";
            $values[] = $limit;

            if ($offset !== '') {
                $sql .= " OFFSET %d";
                $values[] = $offset;
            }
        } else {
            $sql .= " LIMIT 1000";
        }

        if (!empty($values)) {
            $prepared_sql = $wpdb->prepare($sql, $values);
            return $prepared_sql ? $wpdb->get_results($prepared_sql, ARRAY_A) : [];
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public static function insert_record($table, $data)
    {
        global $wpdb;

        if (empty($data)) {
            return null;
        }

        $table = $wpdb->prefix . $table;
        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    public static function update_record($table, $data, $id)
    {
        global $wpdb;

        if (empty($id)) {
            return;
        }

        $table = $wpdb->prefix . $table;
        $data['updated_at'] = current_time('mysql');

        $wpdb->update($table, $data, array ('id' => $id));
    }

    public static function update_records($table, $retrieve_data, $record_params, $update_data)
    {
        if (isset($retrieve_data[0]) && !isset($retrieve_data[0]['limit'])) {
            $retrieve_data[0]['limit'] = 1000;
        }

        $records = self::get_records($table, $retrieve_data);

        foreach ($records as $record) {
            $record_key = $record[$record_params['reference_key']] ?? null;
            if ($record_key && isset($update_data[$record_key])) {
                $update_value = $update_data[$record_key];
                self::update_record($table, [$record_params['update_value'] => $update_value], $record['id']);
            }
        }

        foreach ($update_data as $key => $value) {
            $existing_keys = array_column($records, $record_params['reference_key']);
            if (!in_array($key, $existing_keys)) {
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
        $ids = (array)$ids;

        if ($table_name === Growtype_Art_Database::MODELS_TABLE) {
            $settings = self::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                [
                    'key' => 'model_id',
                    'values' => $ids,
                ]
            ]);

            if (!empty($settings)) {
                self::delete_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, array_column($settings, 'id'));
            }

            $model_image = self::get_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                [
                    'key' => 'model_id',
                    'values' => $ids,
                ]
            ]);

            if (!empty($model_image)) {
                self::delete_records(Growtype_Art_Database::IMAGES_TABLE, array_column($model_image, 'image_id'));
            }
        } elseif ($table_name === Growtype_Art_Database::IMAGES_TABLE) {
            $image_settings = self::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            if (!empty($image_settings)) {
                self::delete_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, array_column($image_settings, 'id'));

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

            if (!empty($model_image)) {
                self::delete_records(Growtype_Art_Database::MODEL_IMAGE_TABLE, array_column($model_image, 'id'));
            }
        } elseif ($table_name === Growtype_Art_Database::IMAGE_SETTINGS_TABLE) {
            $image_setting = self::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => $ids,
                ]
            ]);

            if (!empty($image_setting)) {
                self::delete_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, array_column($image_setting, 'id'));
            }
        }

        $sanitized_ids = implode(',', array_map('absint', $ids));
        if ($sanitized_ids) {
            $wpdb->query("DELETE FROM " . esc_sql($table) . " WHERE ID IN($sanitized_ids)");
        }
    }

    public static function delete_single_record($table_name, $params)
    {
        global $wpdb;

        if (empty($params)) {
             return;
        }

        $table = $wpdb->prefix . $table_name;

        $query_where = [];
        $values = [];
        foreach ($params as $param) {
            if (!isset($param['key']) || !isset($param['value'])) continue;
            $query_where[] = esc_sql($param['key']) . " = %s";
            $values[] = $param['value'] ?? '';
        }

        if (empty($query_where)) return;

        $query = "DELETE FROM " . esc_sql($table) . " WHERE " . implode(' AND ', $query_where);
        $prepared_query = $wpdb->prepare($query, $values);

        if ($prepared_query) {
            $wpdb->query($prepared_query);
        }
    }

    public static function custom_query($sql, $params = [])
    {
        global $wpdb;

        if (empty($sql)) {
            error_log('Growtype Art - DEBUG: custom_query received empty SQL');
            return [];
        }

        if (!empty($params)) {
            // Sanitize params array for PHP 8.1+
            $sanitized_params = array_map(function($p) {
                return is_array($p) ? json_encode($p) : ($p ?? '');
            }, (array)$params);
            $prepared_sql = $wpdb->prepare($sql, ...$sanitized_params);
            
            if (!$prepared_sql) {
                error_log('Growtype Art - DEBUG: prepare returned null in custom_query. SQL: ' . ($sql ?? 'NULL') . ' Params: ' . json_encode($params) . ' Backtrace: ' . wp_debug_backtrace_summary());
            }
        } else {
            $prepared_sql = $sql;
        }

        return $prepared_sql ? $wpdb->get_results($prepared_sql, ARRAY_A) : [];
    }
}
