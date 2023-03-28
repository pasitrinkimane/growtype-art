<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Tag\ImageTag;
use Cloudinary\Transformation\Adjust;
use Cloudinary\Transformation\Delivery;
use Cloudinary\Transformation\Effect;

class Cloudinary_Crud
{
    public function __construct()
    {
        $config = new Configuration();
        $config->cloud->cloudName = get_option('growtype_ai_cloudinary_cloudname');
        $config->cloud->apiKey = get_option('growtype_ai_cloudinary_apikey');
        $config->cloud->apiSecret = get_option('growtype_ai_cloudinary_apisecret');
        $config->url->secure = true;

        $this->cloudinary = new Cloudinary($config);
    }

    //https://res.cloudinary.com/dmm4mlnmq/image/upload/w_5.8,c_scale/q_100/v1678257789/leonardoai/f8678b9703ec56106a2935aa617f6fcb/64082e7d3aee0.jpg
    //https://res.cloudinary.com/dmm4mlnmq/image/upload/e_auto_brightness/q_100/e_vectorize:colors:500:detail:10.9/v1/leonardoai/f8678b9703ec56106a2935aa617f6fcb/640754c1444ab.svg
    //https://wp-basic.test/wp/wp-admin/admin.php?post_type=growtype_ai_models&page=growtype-ai-models&action=sync-images&item=190
    public function adjust_image($public_id, $method, $value)
    {
        switch ($method) {
            case 'brightness':
                if ($value === 'auto') {
                    $transformed_image_url = (string)$this->cloudinary->image($public_id)->adjust(Adjust::autoBrightness())->delivery(Delivery::quality(100))->toUrl();
                } else {
                    $transformed_image_url = (string)$this->cloudinary->image($public_id)->adjust(Adjust::brightness()->level($value))->delivery(Delivery::quality(100))->toUrl();
                }
                break;
            case 'vector':
                $transformed_image_url = (string)$this->cloudinary->image($public_id)->delivery(Delivery::quality(100))->effect(Effect::vectorize()->numOfColors($value)->detailsLevel(1.0))->toUrl();
                break;
        }

        return $transformed_image_url;
    }

    public function upload_asset($file_url, $options)
    {
        try {
            $upload = json_encode($this->cloudinary->uploadApi()->upload($file_url, $options), JSON_PRETTY_PRINT);
            return json_decode($upload, true);
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }

    public function get_assets($options)
    {
        $response = json_encode($this->cloudinary->AdminApi()->assets($options), JSON_PRETTY_PRINT);

        return json_decode($response, true);
    }

    public function get_folder_url($folder_name)
    {
        return null;
    }

    public function delete_folder($folder_name)
    {
        $assets = $this->get_assets([
            'resource_type' => 'image',
            'type' => 'upload',
            'prefix' => $folder_name,
            'max_results' => 200
        ]);

        $resources = $assets['resources'];

        if (!empty($resources)) {
            $public_ids = array_pluck($resources, 'public_id');
            $this->cloudinary->AdminApi()->deleteAssets($public_ids, $options = []);
        }

        try {
            $response = $this->cloudinary->AdminApi()->deleteFolder($folder_name);
            $response = json_encode($response, JSON_PRETTY_PRINT);

            return json_decode($response, true);
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    public function sync_images($model_id)
    {
        $model = growtype_ai_get_model_details($model_id);

        if (empty($model)) {
            return;
        }

        $existing_images = growtype_ai_get_model_images($model_id);

        $external_images = $this->get_assets([
            'resource_type' => 'image',
            'type' => 'upload',
            'prefix' => $model['image_folder'],
            'max_results' => 50,
            'tags' => true,
            'context' => true
        ]);

        $external_images = $external_images['resources'];

        $external_images_asset_ids = array_pluck($external_images, 'asset_id');
        $existing_images_asset_ids = !empty($existing_images) ? array_pluck($existing_images, 'reference_id') : [];

        $img_to_delete = array_diff($existing_images_asset_ids, $external_images_asset_ids);

        if (!empty($img_to_delete)) {
            $images_to_delete = Growtype_Ai_Database::get_records(Growtype_Ai_Database::IMAGES_TABLE, [
                [
                    'key' => 'reference_id',
                    'values' => $img_to_delete,
                ]
            ]);

            Growtype_Ai_Database::delete_records(Growtype_Ai_Database::IMAGES_TABLE, array_pluck($images_to_delete, 'id'));
        }

        foreach ($external_images as $image) {
            if (!in_array($image['asset_id'], $existing_images_asset_ids)) {
                $parts = explode('/', $image['public_id']);
                $new_name = end($parts);
                $image_id = Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGES_TABLE, [
                    'name' => $new_name,
                    'extension' => $image['format'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'location' => 'cloudinary',
                    'folder' => $image['folder'],
                    'reference_id' => $image['asset_id'],
                ]);

                Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, ['model_id' => $model_id, 'image_id' => $image_id]);
            }

            $tags = isset($image['tags']) ? $image['tags'] : null;
            $alt_text = isset($image['context']['custom']['alt']) ? $image['context']['custom']['alt'] : null;
            $caption_text = isset($image['context']['custom']['caption']) ? $image['context']['custom']['caption'] : null;

            if (empty($tags) || empty($alt_text) || empty($caption_text)) {
                $existing_image = Growtype_Ai_Database::get_single_record(Growtype_Ai_Database::IMAGES_TABLE, [
                    [
                        'key' => 'reference_id',
                        'values' => [$image['asset_id']],
                    ]
                ]);

                $image_id = $existing_image['id'];

                $this->update_cloudinary_image_details($image_id);
            }
        }
    }

    public function get_asset_details($public_id, $options = [])
    {
        try {
            $response = $this->cloudinary->AdminApi()->asset($public_id, $options);
            $response = json_encode($response, JSON_PRETTY_PRINT);
            return json_decode($response, true);
        } catch (Exception $e) {
            return null;
        }
    }

    public function update_cloudinary_images_details($model_id = null)
    {
        if (empty($model_id)) {
            $models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE);
        } else {
            $models = [growtype_ai_get_model_details($model_id)];
        }

        foreach ($models as $model) {
            $images = growtype_ai_get_model_images($model['id']);

            foreach ($images as $image) {
                $this->update_cloudinary_image_details($image['id']);
            }
        }
    }

    public function update_cloudinary_image_details($image_id)
    {
        $image = growtype_ai_get_image_details($image_id);

        if (empty($image)) {
            return null;
        }

        $public_id = growtype_ai_get_cloudinary_public_id($image);

        $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : null;
        $title = isset($image['settings']['caption']) ? $image['settings']['caption'] : null;
        $description = isset($image['settings']['alt_text']) ? $image['settings']['alt_text'] : null;

        $image_meta = $this->get_asset_details($public_id);

        if (empty($image_meta)) {
            return null;
        }

        /**
         * Add tags
         */
        if (isset($image_meta['tags'])) {
            Growtype_Ai_Database::delete_single_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'value' => $image_id,
                ],
                [
                    'key' => 'meta_key',
                    'value' => 'tags',
                ]
            ]);

            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'tags',
                'meta_value' => json_encode($image_meta['tags'])
            ]);
        } else {
            if (!empty($tags)) {
                $this->cloudinary->uploadApi()->addTag($tags, [$public_id], $options = []);
            }
        }

        if (isset($image_meta['context']['custom']['alt'])) {

            Growtype_Ai_Database::delete_single_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'value' => $image_id,
                ],
                [
                    'key' => 'meta_key',
                    'value' => 'alt_text',
                ]
            ]);

            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'alt_text',
                'meta_value' => $image_meta['context']['custom']['alt']
            ]);

        } else {
            if (!empty($title)) {
                $this->cloudinary->uploadApi()->addContext([
                    'caption' => $title
                ], [$public_id], $options = []);
            }
        }

        if (isset($image_meta['context']['custom']['caption'])) {

            Growtype_Ai_Database::delete_single_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'image_id',
                    'value' => $image_id,
                ],
                [
                    'key' => 'meta_key',
                    'value' => 'caption',
                ]
            ]);

            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'caption',
                'meta_value' => $image_meta['context']['custom']['caption']
            ]);

        } else {
            if (!empty($description)) {
                $this->cloudinary->uploadApi()->addContext([
                    'alt' => $description,
                ], [$public_id], $options = []);
            }
        }
    }

    public function add_context($public_id, $context)
    {
        $this->cloudinary->uploadApi()->addContext($context, [$public_id], $options = []);
    }
}

