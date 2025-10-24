<?php

use Orhanerday\OpenAi\OpenAi;

class Openai_Base_Image extends Openai_Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function generate_text_content($text, $type)
    {
        if (empty($text)) {
            return '';
        }

//        error_log('GPT generating content');
        $open_ai = new OpenAi($this->open_ai_key);

        $text = str_replace('{prompt_variables}', '', $text);

        $content = '';
        $already_used_answers = [];
        for ($i = 0; $i < 2; $i++) {
            $ignorance_text = 'Do not give answers like ' . implode(',', $already_used_answers) . '.';
            switch ($type) {
                case 'title':
                case 'caption':
                    $content = "Create creative, short artwork title from few words without artist name and without quotes, dots, question marks or punctuation marks, inspired by text - '" . $text . "'. " . $ignorance_text;
                    break;
                case 'description':
                case 'alt_text':
                    $content = "Create short, modest artwork description, from maximum 3 sentences, inspired by text - '" . $text . "'. Do not use quotes, do not mention measuring, format, technique, product type, words like 'canvas, print' or artists names. " . $ignorance_text;
                    break;
                case 'tags':
                    $content = "return only array, (no extra text or sentences), with maximum 20 single words, without numbers, extracted from text - '" . $text . "'. Select only descriptive words (not general like f.e. 'style,art') that later could be used for search purpose. " . $ignorance_text;
                    break;
            }

            $complete = $open_ai->chat([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "You are a helpful assistant."
                    ],
                    [
                        "role" => "user",
                        "content" => $content
                    ],
                ],
                'temperature' => 1.0,
                'max_tokens' => 3000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            $completion = json_decode($complete, true);

            $content = isset($completion['choices'][0]['message']['content']) ? $completion['choices'][0]['message']['content'] : null;

            if (strpos($content, "Im sorry") !== false || strpos($content, "Sure! Here is") !== false) {
                $content = null;
            }

            if (!empty($content)) {

                array_push($already_used_answers, $content);

                if ($type === 'tags') {
                    $decoded_content = json_decode($content, true);

                    if (!empty($decoded_content)) {
                        $content = implode(',', $decoded_content);
                        $content = strtolower($content);
                        $content = explode(',', $content);
                        $content = json_encode($content);
                    } else {
                        $content = str_replace("'", "", $content);
                        $content = str_replace("[", "", $content);
                        $content = str_replace("]", "", $content);

                        if (strpos($content, ",") !== false) {
                            $content = explode(',', $content);
                        } else {
                            $content = explode(' ', $content);
                        }

                        $cleaned_content = [];
                        foreach ($content as $word) {
                            array_push($cleaned_content, trim(strtolower($word)));
                        }

                        $content = json_encode($cleaned_content);
                    }
                } elseif ($type === 'caption') {
                    $content = str_replace('"', "", $content);
                    $content = str_replace("'", "", $content);
                }

                if (!empty($content) && ($type === 'caption' || $type === 'title')) {
                    global $wpdb;
                    $records = $wpdb->get_results("SELECT meta_value FROM wp_growtype_art_image_settings where meta_key='caption' and meta_value != '' group by meta_value", ARRAY_A);

                    if (in_array($content, array_pluck($records, 'meta_value'))) {
                        $content = null;
                    }
                }
            }

            if (!empty($content)) {
                break;
            }
        }

        return $content;
    }

    public function format_models($generation_type = null, $regenerate_values = false, $model_id = null)
    {
        if (!empty($model_id)) {
            $models = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE, [
                [
                    'key' => 'id',
                    'values' => [$model_id],
                ]
            ]);
        } else {
            $models = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE);
        }

        $generation_types = [
            'title' => [
                'meta_key' => 'title',
                'encode' => false,
            ],
            'tags' => [
                'meta_key' => 'tags',
                'encode' => true,
            ],
            'description' => [
                'meta_key' => 'description',
                'encode' => false,
            ],
        ];

        if (!empty($generation_type)) {
            $generation_types = [$generation_types[$generation_type]];
        }

        foreach ($generation_types as $type) {
            foreach ($models as $model) {
                Growtype_Cron_Jobs::create_if_not_exists('generate-model-content', json_encode([
                    'meta_key' => $type['meta_key'],
                    'model_id' => $model_id,
                    'encode' => $type['encode'],
                    'prompt' => $model['prompt'],
                ]), 30);
            }
        }
    }

    public static function get_model_data_for_generating($model_id)
    {
        $character_details = growtype_art_get_model_character_details($model_id);

        $data = [];
        foreach ($character_details as $character_detail) {
            $data[$character_detail['meta_key']] = $character_detail['meta_value'];
        }

        return $data;
    }

    public function format_model_character($model_id)
    {
        $data = self::get_model_data_for_generating($model_id);

        if (!empty($data)) {
            $content = $this->generate_model_character_content($data);

            self::update_character_details($model_id, $content);
        }
    }

    public static function update_character_details($model_id, $content)
    {
        if (!empty($content) && is_array($content)) {
            foreach ($content as $key => $value) {

                if (empty($value)) {
                    continue;
                }

                Growtype_Art_Database_Crud::update_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE,
                    [
                        [
                            'key' => 'model_id',
                            'values' => [$model_id]
                        ]
                    ],
                    [
                        'reference_key' => 'meta_key',
                        'update_value' => 'meta_value'
                    ],
                    [
                        $key => sanitize_textarea_field($value)
                    ]
                );
            }
        }
    }

    public function format_model_images($model_id = null, $regenerate_values = false)
    {
        if (empty($model_id)) {
            $models = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE);
        } else {
            $models = [growtype_art_get_model_details($model_id)];
        }

        foreach ($models as $model) {
            $images = growtype_art_get_model_images_grouped($model['id'])['original'] ?? [];

            foreach ($images as $image) {
                $this->format_image_job($image['id'], $regenerate_values);
            }
        }
    }

    public function format_image($image_id, $regenerate_values = false)
    {
        $Generate_Image_Content_Job = new Generate_Image_Content_Job();
        $Generate_Image_Content = $Generate_Image_Content_Job->run([
            'image_id' => $image_id,
            'regenerate_content' => true,
        ]);

        return $Generate_Image_Content;
    }

    public function format_image_job($image_id, $regenerate_values = false)
    {
        Growtype_Cron_Jobs::create_if_not_exists('generate-image-content', json_encode([
            'image_id' => $image_id,
            'regenerate_content' => $regenerate_values,
        ]), 5);
    }

    public static function message_for_generating_character_description($data)
    {
        $gender = $data['character_gender'] ?? '';
        $nationality = $data['character_nationality'] ?? '';
        $occupation = $data['character_occupation'] ?? '';

        if (empty($gender) || empty($nationality) || empty($occupation)) {
            return '';
        }

        return sprintf(growtype_art_generate_character_prompt(),
            $gender, $occupation, $nationality,
            json_encode($data));
    }

    public function generate_model_character_content($data)
    {
        $open_ai = new OpenAi($this->open_ai_key);

        $messages_content = self::message_for_generating_character_description($data);

        $complete = $open_ai->chat([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    "role" => "system",
                    "content" => "You are a helpful assistant."
                ],
                [
                    "role" => "user",
                    "content" => $messages_content
                ],
            ],
            'temperature' => 0.5,
            'max_tokens' => 3000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        $completion = json_decode($complete, true);

        $content = isset($completion['choices'][0]['message']['content']) ? $completion['choices'][0]['message']['content'] : null;

        $content = json_decode($content, true);

        return $content;
    }
}

