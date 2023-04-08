<?php

use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;

class Extract_Image_Colors_Job
{
    public function run($job_payload)
    {
        $image_id = isset($job_payload['image_id']) ? $job_payload['image_id'] : null;
        $image = growtype_ai_get_image_details($image_id);

        if (!isset($image['settings']['main_colors'])) {
            try {
                self::update_image_colors_groups($image_id);

            } catch (Exception $e) {
                throw new Exception($e);
            }
        }
    }

    public static function get_image_colors_groups($image_id)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '100');
        set_time_limit(100);

        $img_path = growtype_ai_get_image_path($image_id);

        $palette = Palette::fromFilename($img_path);

        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract(5);

        $color_groups = [];
        foreach ($colors as $color) {
            $color_code = Color::fromIntToHex($color);
            $color_group_name = color_code_to_group($color_code);

            if (!empty($color_group_name)) {
                array_push($color_groups, $color_group_name);
            }
        }

        return array_unique($color_groups);
    }

    public static function update_image_colors_groups($image_id)
    {
        $color_groups = self::get_image_colors_groups($image_id);

        $image = growtype_ai_get_image_details($image_id);

        if (isset($image['settings']['main_colors'])) {
            $image_setting = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'meta_key',
                    'value' => 'main_colors',
                ],
                [
                    'key' => 'image_id',
                    'value' => $image['id'],
                ]
            ], 'where');

            if (!empty($image_setting)) {
                Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'meta_key' => 'main_colors',
                    'meta_value' => json_encode($color_groups),
                ], $image_setting[0]['id']);
            }
        } else {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'main_colors',
                'meta_value' => json_encode($color_groups),
            ]);
        }
    }
}

