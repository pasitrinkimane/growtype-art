<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use Orhanerday\OpenAi\OpenAi;

class Openai_Crud
{

    public function __construct()
    {
        $this->open_ai_key = get_option('growtype_ai_openai_api_key');
    }

    public function generate_content($text, $type)
    {
        $open_ai = new OpenAi($this->open_ai_key);

        switch ($type) {
            case 'title':
                $content = "Create modest artwork title without artist name and without quotes from text - '" . $text . "'";
                break;
            case 'alt-title':
                $content = "Create single alternative title version from title - '" . $text . "'";
                break;
            case 'alt-description':
                $content = "Create single alternative description version from description - '" . $text . "'";
                break;
            case 'tags':
                $content = "return only array with tags extracted from text - '" . $text . "'";
                break;
            case 'description':
                $content = "Create modest artwork description without artist name from text - '" . $text . "'";
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

        return $completion['choices'][0]['message']['content'];
    }

    public function format_models($generation_type = null, $regenerate_values = false, $model_id = null)
    {
        if (!empty($model_id)) {
            $models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE, [
                [
                    'key' => 'id',
                    'values' => [$model_id],
                ]
            ]);
        } else {
            $models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE);
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
            foreach ($models as $key => $model) {

                $existing_content = growtype_ai_get_model_single_setting($model['id'], $type['meta_key']);

                $meta_value = isset($existing_content['meta_value']) ? $existing_content['meta_value'] : null;

                if (!empty($meta_value) && $type['encode']) {
                    $meta_value = json_decode($meta_value, true);
                    $meta_value = is_array($meta_value) ? $meta_value : null;
                }

                if (!$regenerate_values) {
                    if (!empty($meta_value)) {
                        continue;
                    }
                }

                $new_content = $this->generate_content($model['prompt'], $type['meta_key']);

                if ($type['encode']) {
                    $new_content = json_decode($new_content, true);
                    $new_content = json_encode($new_content);
                } else {
                    $new_content = str_replace('"', "", $new_content);
                }

                if (empty($new_content)) {
                    continue;
                }

                /**
                 * tags
                 */
                if (!empty($existing_content)) {
                    $update_record = $regenerate_values;

                    if (!$regenerate_values) {
                        $update_record = empty($meta_value) ? true : false;
                    }

                    if ($update_record) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $model['id'],
                            'meta_key' => $type['meta_key'],
                            'meta_value' => $new_content,
                        ], $existing_content['id']);
                    }
                } else {
                    Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                        'model_id' => $model['id'],
                        'meta_key' => $type['meta_key'],
                        'meta_value' => $new_content,
                    ]);
                }
            }
        }
    }

    public function format_model_images($model_id = null)
    {
        if (empty($model_id)) {
            $models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE);
        } else {
            $models = [growtype_ai_get_model_details($model_id)];
        }

        foreach ($models as $model) {
            $images = growtype_ai_get_model_images($model['id']);

            foreach ($images as $image) {
                $this->format_image($image['id']);
            }
        }
    }

    public function format_image($image_id)
    {
        $model = growtype_ai_get_image_model_details($image_id);

        $tags = $model['settings']['tags'];
        $tags = empty($tags) ? null : json_decode($tags, true);
        $title = $model['settings']['title'];
        $description = $model['settings']['description'];

        if (!isset($image['settings']['caption'])) {
            $alt_title = $this->generate_content($title, 'alt-title');
            $alt_title = str_replace('"', "", $alt_title);
            $alt_title = str_replace("'", "", $alt_title);

            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'caption',
                'meta_value' => $alt_title,
            ]);
        }

        if (!isset($image['settings']['alt_text'])) {
            $alt_description = $this->generate_content($description, 'alt-description');
            $alt_description = str_replace('"', "", $alt_description);

            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'alt_text',
                'meta_value' => $alt_description,
            ]);
        }

        if (!isset($image['settings']['tags'])) {
            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'tags',
                'meta_value' => json_encode($tags),
            ]);
        }
    }
}

