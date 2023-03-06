<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

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

    public function upload_image($file, $folder_name)
    {
        $upload = json_encode($this->cloudinary->uploadApi()->upload($file['url'], [
            'public_id' => $file['name'],
            'folder' => $folder_name,
//            'tags' => $file['tags'],
//            'context' => 'alt=' . $file['alt_text'] . '❘caption=' . $file['caption'],
        ]), JSON_PRETTY_PRINT);

        return json_decode($upload, true);
    }

    public function get_images($folder_name)
    {
        $args = [
            'resource_type' => 'image',
            'type' => 'upload',
            'prefix' => $folder_name,
            'max_results' => 100
        ];

        $response = json_encode($this->cloudinary->AdminApi()->assets($args), JSON_PRETTY_PRINT);

        return json_decode($response, true);
    }

    public function get_folder_url($folder_name)
    {
        return null;
    }

    public function delete_folder($folder_name)
    {
        $resources = $this->get_images($folder_name)['resources'];

        if (!empty($resources)) {
            $public_ids = array_pluck($resources, 'public_id');
            $this->cloudinary->AdminApi()->deleteAssets($public_ids, $options = []);
        }

        try {
            $response = json_encode($this->cloudinary->AdminApi()->deleteFolder($folder_name), JSON_PRETTY_PRINT);

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

        $external_images = $this->get_images($model['image_folder']);
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

    public function get_asset_details($public_id)
    {
        $response = json_encode($this->cloudinary->AdminApi()->asset($public_id, $options = []), JSON_PRETTY_PRINT);
        return json_decode($response, true);
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

        $public_id = $image['folder'] . '/' . $image['name'];

        $tags = isset($image['settings']['tags']) ? $image['settings']['tags'] : null;
        $tags = empty($tags) ? null : json_decode($tags, true);
        $title = isset($image['settings']['caption']) ? $image['settings']['caption'] : null;
        $description = isset($image['settings']['alt_text']) ? $image['settings']['alt_text'] : null;

        $image_meta = $this->get_asset_details($public_id);

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
}

