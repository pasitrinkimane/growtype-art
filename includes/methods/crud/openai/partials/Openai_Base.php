<?php

use Ahc\Json\Fixer;
use Orhanerday\OpenAi\OpenAi;

class Openai_Base
{
    public $open_ai_key;

    public function __construct()
    {
        $this->open_ai_key = self::api_key();
    }

    public static function api_key()
    {
        return get_option('growtype_art_openai_api_key');
    }

    public static function fix_malformed_json($malformedJson)
    {
        error_log('!!!FIXING MALFORMED JSON!!!');

        return (new Fixer)->fix($malformedJson);
    }

    public static function generate($content)
    {
        $open_ai = new OpenAi(Openai_Base::api_key());

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

        $completion_content = isset($completion['choices'][0]['message']['content']) ? $completion['choices'][0]['message']['content'] : null;

        $result = json_decode($completion_content, true);

        if (empty($result)) {
            try {
                $result = json_decode(Openai_Base::fix_malformed_json($completion_content), true);
            } catch (Exception $e) {
                error_log('!!!ERROR FIXING MALFORMED JSON!!!');
            }
        }

        return $result;
    }
}

