<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Generator_Base;

class Promptchan_Base extends Growtype_Art_Generator_Base
{
    private const API_URL = 'https://prod.aicloudnetservices.com/api/external/create';

    public function get_provider_key()
    {
        return Growtype_Art_Crud::PROMPTCHAN_KEY;
    }

    public function get_provider_label(): string
    {
        return 'Promptchan';
    }

    public function get_default_model($params = [])
    {
        return $params['model'] ?? 'hyperreal-xl-plus';
    }

    public function get_models(): array
    {
        $cost = [
            'is_nsfw'   => true,
            'cost_usd'  => null,
            'cost_label'=> '1–1.25 Gems / image',
        ];

        return [
            'hyperreal-xl-plus' => array_merge($cost, ['label' => 'Hyperreal XL+']),
            'photo-xl-plus'     => array_merge($cost, ['label' => 'Photo XL+']),
            'realism-xl-2'      => array_merge($cost, ['label' => 'Realism XL 2']),
            'cinematic'         => array_merge($cost, ['label' => 'Cinematic']),
            'hardcore-xl'       => array_merge($cost, ['label' => 'Hardcore XL']),
        ];
    }

    private function api_style(string $model): string
    {
        $styles = [
            'hyperreal-xl-plus' => 'Hyperreal XL+',
            'photo-xl-plus'     => 'Photo XL+',
            'realism-xl-2'      => 'Cinematic Flame',
            'cinematic'         => 'Cinematic',
            'hardcore-xl'       => 'Hardcore XL',
        ];

        return $styles[$model] ?? 'Hyperreal XL+';
    }

    private function image_size(array $params): string
    {
        $width = (int) ($params['width'] ?? 0);
        $height = (int) ($params['height'] ?? 0);

        if (!empty($params['_auto_dimensions']) || $width < 1 || $height < 1) {
            return '512x768';
        }

        if (abs($width - $height) / max($width, $height) < 0.12) {
            return '512x512';
        }

        return $width > $height ? '768x512' : '512x768';
    }

    public function generate_image_init($params)
    {
        $api_key = $params['token'] ?? '';
        if (empty($api_key) || $api_key === 'not-needed') {
            return [
                'success' => false,
                'message' => 'Promptchan API key is not configured.',
            ];
        }

        $payload = [
            'style'         => $this->api_style($params['model'] ?? 'hyperreal-xl-plus'),
            'prompt'        => $params['prompt'] ?? '',
            'quality'       => $params['quality'] ?? 'Ultra',
            'image_size'    => $this->image_size($params),
            'negative_prompt' => $params['negative_prompt'] ?? '',
            'seed'          => isset($params['seed']) ? (int) $params['seed'] : -1,
            'creativity'    => isset($params['creativity']) ? (int) $params['creativity'] : 50,
            'restore_faces' => !empty($params['restore_faces']),
            'age_slider'    => max(18, (int) ($params['age_slider'] ?? 21)),
        ];

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $api_key,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 180,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($http_code >= 400 || !is_array($decoded)) {
            return [
                'success' => false,
                'message' => is_array($decoded)
                    ? ($decoded['message'] ?? $decoded['error'] ?? 'Promptchan generation failed.')
                    : 'Promptchan returned an invalid response.',
            ];
        }

        $image = $decoded['image'] ?? '';
        if (!is_string($image) || $image === '') {
            return [
                'success' => false,
                'message' => $decoded['message'] ?? 'Promptchan did not return an image.',
            ];
        }

        if (str_starts_with($image, 'data:image/') && str_contains($image, ',')) {
            $image = explode(',', $image, 2)[1];
        }

        return [
            'success'          => true,
            'imageBase64'      => $image,
            '_request_payload' => array_merge(['_url' => self::API_URL], $payload),
        ];
    }
}
