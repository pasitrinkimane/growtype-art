<?php

use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;

class Extract_Image_Colors_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $image_id = isset($job_payload['image_id']) ? $job_payload['image_id'] : null;
        $update_colors = isset($job_payload['update_colors']) ? $job_payload['update_colors'] : false;
        $image = growtype_ai_get_image_details($image_id);

        if ($update_colors || (!isset($image['settings']['main_colors']) || empty($image['settings']['main_colors']))) {
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

        if (empty($img_path)) {
            return;
        }

        $palette = Palette::fromFilename($img_path);

        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract(5);

        $color_groups = [];
        foreach ($colors as $color) {
            $color_code = Color::fromIntToHex($color);
            $color_group_name = growtype_ai_color_code_to_group($color_code);

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

    public static function get_image_details($image_id, $html = false)
    {
        $img_path = growtype_ai_get_image_path($image_id);
        $palette = Palette::fromFilename($img_path);
        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract(5);

        $color_codes = [];
        foreach ($colors as $color) {
            $color_code = Color::fromIntToHex($color);

            array_push($color_codes, $color_code);
        }

        $image_details = [
            'url' => growtype_ai_get_image_url($image_id),
            'colors' => $color_codes,
            'groups' => Extract_Image_Colors_Job::get_image_colors_groups($image_id),
        ];

        if ($html) {
            ob_start();

            echo '<div><img src="' . growtype_ai_get_image_url($image_id) . '" style="max-width: 250px;"></div>';

            foreach ($color_codes as $color_code) {
                ?>
                <div class="growtype-ai-color" style="background-color: <?php echo $color_code; ?>;">
                    <?php echo $color_code; ?> - <?php echo growtype_ai_hex_to_rgb($color_code); ?>
                </div>
                <?php
            }

            foreach (Extract_Image_Colors_Job::get_image_colors_groups($image_id) as $color_group) {
                ?>
                <div class="growtype-ai-color" style="">
                    <?php echo $color_group; ?>
                </div>
                <?php
            }

            $image_details = ob_get_clean();
        }

        return $image_details;
    }
}

