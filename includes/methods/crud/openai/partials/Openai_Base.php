<?php

require_once GROWTYPE_ART_PATH . '/vendor/autoload.php';

use Ahc\Json\Fixer;
use Orhanerday\OpenAi\OpenAi;

class Openai_Base
{
    public $open_ai_key;

    public function __construct()
    {
        $this->open_ai_key = self::api_key();
    }

    public function get_provider_label(): string
    {
        return 'OpenAI';
    }

    public function get_text_models(): array
    {
        return [
            'gpt-4o'        => 'GPT-4o',
            'gpt-4o-mini'   => 'GPT-4o mini',
            'gpt-4-turbo'   => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        ];
    }

    /**
     * Generate text content via OpenAI Chat Completions.
     * Named generate_chat_content() (not generate_text_content) to avoid
     * a signature conflict with Openai_Base_Image::generate_text_content($text, $type).
     */
    public function generate_chat_content(string $prompt, string $model = 'gpt-4o-mini'): ?string
    {
        return self::generate_content_with_model($prompt, $model);
    }

    public static function generate_content_with_model(string $content, string $model = 'gpt-4o-mini'): ?string
    {
        if (!class_exists('Orhanerday\\OpenAi\\OpenAi')) {
            error_log('Growtype Art - OpenAi library not found');
            return null;
        }

        $api_key = self::api_key();
        if (empty($api_key)) {
            error_log('Growtype Art - OpenAI API Key is empty');
            return null;
        }

        $open_ai  = new \Orhanerday\OpenAi\OpenAi($api_key);
        $response = $open_ai->chat([
            'model'             => $model,
            'messages'          => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user',   'content' => $content],
            ],
            'temperature'       => 1.0,
            'max_tokens'        => 4000,
            'frequency_penalty' => 0,
            'presence_penalty'  => 0,
        ]);

        if (empty($response)) {
            error_log('Growtype Art - OpenAI response is empty');
            return null;
        }

        $decoded = json_decode($response, true);

        if (isset($decoded['error'])) {
            error_log('Growtype Art - OpenAI Error: ' . json_encode($decoded['error']));
            return null;
        }

        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    public static function api_key()
    {
        // Prefer credentials from the growtype-auth plugin.
        if (class_exists('Growtype_Auth')) {
            $credentials = Growtype_Auth::credentials(Growtype_Auth::SERVICE_OPENAI);
            if (!empty($credentials)) {
                // Use the first group's api_key.
                $first_group = reset($credentials);
                if (!empty($first_group['api_key'])) {
                    return trim($first_group['api_key']);
                }
            }
        }

        // Legacy fallback: key stored directly in growtype-art settings.
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
            'model' => 'gpt-4o-mini',
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

    public static function generate_content($content)
    {
        if (!class_exists('Orhanerday\OpenAi\OpenAi')) {
            error_log('Growtype Art - OpenAi library not found');
            return null;
        }

        $api_key = self::api_key();
        if (empty($api_key)) {
            error_log('Growtype Art - API Key is empty');
            return null;
        }

        $open_ai = new OpenAi($api_key);

        $complete = $open_ai->chat([
            'model' => 'gpt-4o-mini',
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

        if (empty($complete)) {
            error_log('Growtype Art - Raw OpenAI response is empty');
            return null;
        }

        $completion = json_decode($complete, true);

        if (isset($completion['error'])) {
            error_log('Growtype Art - OpenAI Error: ' . json_encode($completion['error']));
            return null;
        }

        return isset($completion['choices'][0]['message']['content']) ? $completion['choices'][0]['message']['content'] : null;
    }
}
