<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Admin\AdminApi;

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
            'folder' => $folder_name
        ]), JSON_PRETTY_PRINT);

        return json_decode($upload, true);
    }

    public function get_images($folder_name)
    {
        $response = json_encode($this->cloudinary->AdminApi()->assets([
            'resource_type' => 'image',
            'type' => 'upload',
            'prefix' => $folder_name,
            'max_results' => 100
        ]), JSON_PRETTY_PRINT);

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
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/cloudinary/Cloudinary_Crud.php';
        $cloudinary_crud = new Cloudinary_Crud();

        $model = Growtype_Ai_Database::get_single_record(Growtype_Ai_Database::MODELS_TABLE, [
            [
                'key' => 'id',
                'values' => [$model_id],
            ]
        ]);

        if (empty($model)) {
            return;
        }

        $existing_images = Growtype_Ai_Database::get_pivot_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, Growtype_Ai_Database::IMAGES_TABLE, 'image_id', [
                [
                    'key' => 'model_id',
                    'values' => [$model_id],
                ]
            ]
        );

        $external_images = $cloudinary_crud->get_images($model['image_folder']);
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
            if (!in_array($image['asset_id'], array_pluck($existing_images, 'reference_id'))) {
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
        }
    }

    public function sync_existing_images_with_cloudinary()
    {
        $images = Growtype_Ai_Database::get_records(Growtype_Ai_Database::IMAGES_TABLE);

//        d(count($images));

        $tags_string = [];
        foreach ($images as $image) {

            $model = growtype_ai_get_image_model_details($image['id']);

            $tags = $model['prompt'];

            $tags_string[preg_replace("/\s+/", "", $tags)] = $tags;

//            dd($tags);
//            $tags = preg_replace("/\b\S{1,3}\b/", "", $tags);
//            $tags = preg_split("/\s+/", $tags);
//            $tags = array_filter($tags);
//
//            d($tags);

//            $image['url'] = 'https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];
//            $image['location'] = 'locally';
//
//            growtype_ai_save_file($image, $image['folder']);

//            $this->upload_image([
//                'url' => 'https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'],
//                'name' => $image['name']
//            ], $image['folder']);
        }

        d(implode(" ", array_values($tags_string)));
    }
}

