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
        error_log('GPT generating content');

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

        $content = $completion['choices'][0]['message']['content'];

        if (strpos($content, "Im sorry") !== false) {
            $content = null;
        }

        if (!empty($content) && $type == 'tags') {
            $content = json_decode($content, true);

            if (!empty($content)) {
                $content = implode(',', $content);
                $content = strtolower($content);
                $content = explode(',', $content);
                $content = json_encode($content);
            }
        }

        return $content;
    }

    public function format_models($generation_type = null, $regenerate_values = false, $model_id = null)
    {
        if (!empty($model_id)) {
            $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE, [
                [
                    'key' => 'id',
                    'values' => [$model_id],
                ]
            ]);
        } else {
            $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE);
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
                growtype_ai_init_job('generate-model-content', json_encode([
                    'meta_key' => $type['meta_key'],
                    'model_id' => $model_id,
                    'encode' => $type['encode'],
                    'prompt' => $model['prompt'],
                ]), 30);
            }
        }
    }

    public function format_model_images($model_id = null)
    {
        if (empty($model_id)) {
            $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE);
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
        growtype_ai_init_job('generate-image-content', json_encode([
            'image_id' => $image_id
        ]), 30);
    }
}

