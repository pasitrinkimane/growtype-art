<?php

class Growtype_Art_Crud
{
    const LEONARDOAI_KEY = 'leonardoai';
    const PICLUMEN_KEY = 'piclumen';
    const AIEASE_KEY = 'aiease';
    const RUNWARE_KEY = 'runware';
    const FAL_KEY = 'fal';
    const SEGMIND_KEY = 'segmind';
    const GEMINI_KEY = 'gemini';

    const POLLINATIONS_KEY = 'pollinations';

    const FLATAI_KEY = 'flatai';
    const WRITECREAM_KEY = 'writecream';
    const FREEFLUX_KEY = 'freeflux';

    const PERCHANCE_KEY = 'perchance';

    const REPLICATE_KEY = 'replicate';

    const TOGETHERAI_KEY = 'togetherai'; // Not implemented

    const XAI_KEY = 'xai';

    const PROMPTCHAN_KEY = 'promptchan';

    const DEFAULT_IMAGE_PROVIDER = self::XAI_KEY;

    /**
     * Providers that support text / content generation.
     * Each corresponding *_Base class must implement get_text_models().
     * Extensible via the `growtype_art_text_providers` filter.
     */
    const API_GENERATE_TEXT_PROVIDERS = [
        'openai',
        self::GEMINI_KEY,
        self::XAI_KEY,
    ];

    const DEFAULT_GENERATABLE_IMAGES_LIMIT = 5;

    const NSFW_PROVIDERS = [
        self::RUNWARE_KEY,
        self::POLLINATIONS_KEY,
        self::FAL_KEY,
        self::XAI_KEY,
        self::REPLICATE_KEY,
        self::PROMPTCHAN_KEY,
//        self::SEGMIND_KEY,
//        self::FREEFLUX_KEY,
//        self::WRITECREAM_KEY,
    ];

    const API_GENERATE_IMAGE_PROVIDERS = [
        self::SEGMIND_KEY,
        self::GEMINI_KEY,
        self::RUNWARE_KEY,
        self::FAL_KEY,
        self::POLLINATIONS_KEY,
        self::AIEASE_KEY,
        self::PICLUMEN_KEY,
        self::XAI_KEY,
        self::REPLICATE_KEY,
        self::PROMPTCHAN_KEY,
    ];

    const API_GENERATE_VIDEO_PROVIDERS = [
        self::REPLICATE_KEY,
    ];

    const PROVIDERS_TO_INSTANTLY_GENERATE_IMAGES = [
        self::SEGMIND_KEY,
        self::GEMINI_KEY,
        self::RUNWARE_KEY,
        self::FAL_KEY,
        self::XAI_KEY,
        self::POLLINATIONS_KEY,
        self::REPLICATE_KEY,
        self::PROMPTCHAN_KEY,
    ];

    const MODEL_GENERATE_IMAGE_PROVIDERS = [
        Growtype_Art_Crud::LEONARDOAI_KEY => Growtype_Art_Crud::LEONARDOAI_KEY,
        Growtype_Art_Crud::PICLUMEN_KEY => Growtype_Art_Crud::PICLUMEN_KEY,
        Growtype_Art_Crud::AIEASE_KEY => Growtype_Art_Crud::AIEASE_KEY,
        Growtype_Art_Crud::POLLINATIONS_KEY => Growtype_Art_Crud::POLLINATIONS_KEY,
        Growtype_Art_Crud::TOGETHERAI_KEY => Growtype_Art_Crud::TOGETHERAI_KEY,
        Growtype_Art_Crud::FREEFLUX_KEY => Growtype_Art_Crud::FREEFLUX_KEY,
        Growtype_Art_Crud::RUNWARE_KEY => Growtype_Art_Crud::RUNWARE_KEY,
        Growtype_Art_Crud::GEMINI_KEY => Growtype_Art_Crud::GEMINI_KEY,
        Growtype_Art_Crud::SEGMIND_KEY => Growtype_Art_Crud::SEGMIND_KEY,
        Growtype_Art_Crud::FAL_KEY => Growtype_Art_Crud::FAL_KEY,
        Growtype_Art_Crud::WRITECREAM_KEY => Growtype_Art_Crud::WRITECREAM_KEY,
        Growtype_Art_Crud::XAI_KEY => Growtype_Art_Crud::XAI_KEY,
        Growtype_Art_Crud::REPLICATE_KEY => Growtype_Art_Crud::REPLICATE_KEY,
        Growtype_Art_Crud::PROMPTCHAN_KEY => Growtype_Art_Crud::PROMPTCHAN_KEY,
    ];

    const IMAGES_FOLDER_NAME = 'models';

    const HTTP_HEADER = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
        'Accept'     => 'image/jpeg,image/png,image/gif,image/webp,*/*;q=0.8',
        'Connection' => 'keep-alive',
    ];

    public function __construct()
    {
        $this->load_methods();
    }

    public function load_methods()
    {
        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/openai/Openai_Crud.php';
        new Openai_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/leonardoai/Leonardoai_Crud.php';
        new Leonardoai_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/piclumen/Piclumen_Crud.php';
        new Piclumen_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/aiease/Aiease_Crud.php';
        new Aiease_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/flatai/Flatai_Crud.php';
        new Flatai_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/fal/Fal_Crud.php';
        new Fal_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/perchance/Perchance_Crud.php';
        new Perchance_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/runware/Runware_Crud.php';
        new Runware_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/segmind/Segmind_Crud.php';
        new Segmind_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/gemini/Gemini_Crud.php';
        new Gemini_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/pollinations/Pollinations_Crud.php';
        new Pollinations_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/freeflux/Freeflux_Crud.php';
        new Freeflux_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/writecream/Writecream_Crud.php';
        new Writecream_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/togetherai/Togetherai_Crud.php';
        new Togetherai_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/replicate/Replicate_Crud.php';
        new Replicate_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/xai/Xai_Crud.php';
        new Xai_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/promptchan/Promptchan_Crud.php';
        new Promptchan_Crud();

        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/cloudinary/Cloudinary_Crud.php';
        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/tinypng/TinyPng_Crud.php';
        require_once GROWTYPE_ART_PATH . 'includes/methods/crud/resmush/Resmush_Crud.php';
    }

    public static function delete_image($image_id)
    {
        $image_path = growtype_art_get_image_path($image_id);

        if (!empty($image_path)) {
            $directory = pathinfo($image_path, PATHINFO_DIRNAME);
            $filename_without_extension = pathinfo($image_path, PATHINFO_FILENAME);
            $original_extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));

            // Delete the exact file path
            if (file_exists($image_path)) {
                unlink($image_path);
            }

            // Only delete the webp variation if the deleted file was a standard image
            if (in_array($original_extension, ['jpg', 'jpeg', 'png'])) {
                $webp_file = $directory . DIRECTORY_SEPARATOR . $filename_without_extension . '.webp';
                if (file_exists($webp_file)) {
                    unlink($webp_file);
                }
            }
        }

        Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::IMAGES_TABLE, [$image_id]);
    }

    public static function link_mp4_to_parent_image($model_id, $saved_image_id, $file_name, $saved_image_url)
    {
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, ['mp4'])) {
            return;
        }

        $model_images = growtype_art_get_model_images_grouped($model_id, 1000)['original'] ?? [];
        $matched_model_image = null;
        $file_basename_exact = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
        $file_basename_prefix = explode('-', $file_basename_exact)[0];

        // 1. Try exact match first
        foreach ($model_images as $model_image) {
            if ($model_image['extension'] === $file_extension) continue;
            if ($model_image['name'] === $file_basename_exact) {
                $matched_model_image = $model_image;
                break;
            }
        }

        // 2. Fallback to prefix match
        if (!$matched_model_image) {
            foreach ($model_images as $model_image) {
                if ($model_image['extension'] === $file_extension) continue;
                $model_image_prefix = explode('-', $model_image['name'])[0];
                if ($model_image_prefix === $file_basename_prefix) {
                    $matched_model_image = $model_image;
                    break;
                }
            }
        }

        if ($matched_model_image) {
            $parent_image_id = $matched_model_image['id'];
            if (!empty($matched_model_image['settings']['parent_image_id'])) {
                $parent_image_id = $matched_model_image['settings']['parent_image_id'];
            }

            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $parent_image_id,
                'meta_key' => 'video_url_image_id_' . $saved_image_id,
                'meta_value' => $saved_image_url ?? '',
            ]);

            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $saved_image_id,
                'meta_key' => 'parent_image_id',
                'meta_value' => $parent_image_id,
            ]);
        }
    }

    public static function save_image($image, $save_to_db = true, $crop_percent = null)
    {
        if (empty($image) || !is_array($image)) {
            return ['error' => 'Invalid image data'];
        }

        if ($save_to_db && isset($image['id'])) {
            /**
             * Check generated image id, to prevent duplicates
             */
            $existing_generated_image_id = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'meta_key',
                    'value' => 'generated_image_id',
                ],
                [
                    'key' => 'meta_value',
                    'value' => $image['id'],
                ]
            ], 'where');

            if (!empty($existing_generated_image_id)) {
                error_log('Image already exists: ' . $image['id']);
                return [];
            }
        }

        $image_url = $image['motionMP4URL'] ?? $image['url'] ?? '';
        $image_content = $image['content'] ?? '';

        $file_name = isset($image['name']) && !empty($image['name']) ? sanitize_file_name(pathinfo($image['name'], PATHINFO_FILENAME)) : wp_generate_password(24, false);

        if (!empty($image_url)) {
//            $file_name = sanitize_file_name(pathinfo($image_url, PATHINFO_FILENAME));
            $file_name = wp_generate_password(24, false, false);
        }

        if (!empty($image_url)) {
            $validate_file = self::validate_file($image_url);

            if (!$validate_file['is_valid']) {
                error_log(sprintf('Growtype Art - Save file. Validation failed. Reason: %s', $validate_file['message'] ?? 'Unknown reason'));
                return $validate_file;
            }

            $filename = basename($image_url);

            try {
                $dimensions = self::fetch_image_info($image_url);
            } catch (Exception $e) {

                error_log(sprintf('Growtype Art - Save file. Error saving file: %s', $e->getMessage()));

                return [
                    'error' => 'Error saving file: ' . $e->getMessage()
                ];
            }

            $file = [
                'name' => $file_name,
                'extension' => pathinfo($filename, PATHINFO_EXTENSION),
                'width' => $dimensions['width'] ?? '',
                'height' => $dimensions['height'] ?? '',
                'url' => $image_url,
                'folder' => $image['folder'],
                'location' => $image['location'] ?? 'locally',
            ];

            $saved_image = growtype_art_save_external_file($file, $file['folder']);
        } elseif (!empty($image_content)) {
            if (self::is_valid_base64($image_content)) {
                $decoded_data = self::decode_base64_image($image_content);

                if (!isset($decoded_data['tmp_file'])
                    || empty($decoded_data['tmp_file'])
                    || empty($decoded_data)
                    || !$decoded_data['is_valid']) {
                    return ['error' => 'Invalid Base64 image data'];
                }

                $file = [
                    'name' => $file_name,
                    'extension' => $decoded_data['extension'],
                    'width' => $decoded_data['width'],
                    'height' => $decoded_data['height'],
                    'url' => '', // No external URL for Base64 images
                    'folder' => $image['folder'],
                    'location' => $image['location'] ?? 'locally',
                    'tmp_file' => $decoded_data['tmp_file'],
                ];

                $saved_image = growtype_art_save_local_file($file, $image['folder']);
            } else {
                $image_to_save = [
                    'name' => $file_name,
                    'extension' => 'jpg',
                    'folder' => $image['folder'],
                    'content' => $image_content,
                ];

                $saved_image = self::save_raw_image($image_to_save);

                $public_url = $saved_image['url'];
                $local_path = $saved_image['path'];

                $dimensions = getimagesize($public_url);

                $file = [
                    'name' => $file_name,
                    'extension' => pathinfo($local_path, PATHINFO_EXTENSION),
                    'width' => $dimensions[0] ?? '',
                    'height' => $dimensions[1] ?? '',
                    'folder' => $image['folder'],
                    'location' => $image['location'] ?? 'locally',
                ];
            }
        } else {
            $dimensions = getimagesize($image['tmp_name']);

            $file = [
                'name' => $file_name,
                'extension' => pathinfo($image['name'], PATHINFO_EXTENSION),
                'width' => $dimensions[0] ?? '',
                'height' => $dimensions[1] ?? '',
                'folder' => $image['folder'],
                'location' => $image['location'] ?? 'locally',
            ];

            $saved_image = growtype_art_save_local_file($image, $file['folder']);
        }

        if (!empty($image['target_width']) && !empty($image['target_height']) && isset($saved_image['path'])) {
            $target_width  = max(1, (int) $image['target_width']);
            $target_height = max(1, (int) $image['target_height']);
            $editor        = wp_get_image_editor($saved_image['path']);

            if (!is_wp_error($editor)) {
                $resized = $editor->resize($target_width, $target_height, true);
                if (!is_wp_error($resized)) {
                    $saved = $editor->save($saved_image['path']);
                    if (!is_wp_error($saved)) {
                        $file['width']  = (int) ($saved['width'] ?? $target_width);
                        $file['height'] = (int) ($saved['height'] ?? $target_height);
                    }
                }
            }
        }

        if (!empty($crop_percent) && isset($saved_image['path'])) {
            self::crop_image($saved_image['path'], $crop_percent);
        }

        $save_data = [];

        if ($save_to_db) {
            /**
             * Check if record exists
             */
            $existing_record_details = [
                [
                    'key' => 'name',
                    'value' => $file['name'],
                ],
                [
                    'key' => 'extension',
                    'value' => $file['extension'],
                ],
                [
                    'key' => 'location',
                    'value' => $file['location'],
                ],
                [
                    'key' => 'folder',
                    'value' => $file['folder'],
                ],
            ];

            $image_id = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGES_TABLE,
                $existing_record_details,
                'where');

            if (!empty($image_id)) {
                error_log('Image already exists: ' . $file['name']);
                return [];
            }

            /**
             * Save image record
             */
            $image_id = Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGES_TABLE, [
                'name' => $file['name'],
                'extension' => $file['extension'],
                'width' => $file['width'],
                'height' => $file['height'],
                'location' => $file['location'],
                'folder' => $file['folder']
            ]);

            /**
             * Save external id, to prevent duplicates
             */
            if (isset($image['id'])) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $image_id,
                    'meta_key' => 'generated_image_id',
                    'meta_value' => $image['id']
                ]);
            }

            /**
             * Save meta details
             */
            if (isset($image['meta_details'])) {
                foreach ($image['meta_details'] as $key => $meta) {
                    Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                        'image_id' => $image_id,
                        'meta_key' => $meta['key'],
                        'meta_value' => $meta['value']
                    ]);
                }
            }

            /**
             * Update reference id
             */
            if (isset($saved_image['asset_id'])) {
                Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::IMAGES_TABLE, [
                    'reference_id' => $saved_image['asset_id']
                ], $image_id);
            }

            /**
             * Upscale image
             */
            if (get_option('growtype_art_replicate_upscale_uploaded_images', false)) {
                $cloudinary_public_id = $file['folder'] . '/' . $file['name'];
                $upscale_image_url = $saved_image['url'];

                $real_esrgan = new Replicate_Crud();

                $real_esrgan->upscale($upscale_image_url, [
                    'id' => $image['id'],
                    'public_id' => $cloudinary_public_id
                ]);
            }

            do_action('growtype_art_model_image_save', $image_id);

            $save_data['id'] = $image_id;
        }

        $save_data['details'] = $saved_image;

        return $save_data;
    }

    public static function fetch_image_info($image_url)
    {
        $wp_response = wp_remote_get($image_url, [
            'headers' => self::HTTP_HEADER,
            'timeout' => 30,
        ]);

        $http_code  = is_wp_error($wp_response) ? 0 : (int) wp_remote_retrieve_response_code($wp_response);
        $image_data = is_wp_error($wp_response) ? '' : wp_remote_retrieve_body($wp_response);

        if ($http_code == 200 && $image_data) {
            $image_info = getimagesizefromstring($image_data);
            return [
                'width' => $image_info[0] ?? '',
                'height' => $image_info[1] ?? '',
                'mime' => $image_info['mime'] ?? ''
            ];
        }

        return [];
    }

    public static function crop_image($file_path, $percentage)
    {
        try {
            $image = imagecreatefromstring(file_get_contents($file_path));

            if (!$image) {
                throw new Exception("Unable to load image for cropping.");
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate crop area (90% of the original)
            $crop_width = (int)($width * $percentage);
            $crop_height = (int)($height * $percentage);

            // Center the crop
            $x = (int)(($width - $crop_width) / 2);
            $y = (int)(($height - $crop_height) / 2);

            // Perform the crop
            $cropped_image = imagecrop($image, [
                'x' => $x,
                'y' => $y,
                'width' => $crop_width,
                'height' => $crop_height
            ]);

            if (!$cropped_image) {
                throw new Exception("Cropping failed.");
            }

            // Save the cropped image back to the same location
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            switch ($extension) {
                case 'jpeg':
                case 'jpg':
                    imagejpeg($cropped_image, $file_path, 90);
                    break;
                case 'png':
                    imagepng($cropped_image, $file_path, 9);
                    break;
                case 'gif':
                    imagegif($cropped_image, $file_path);
                    break;
                case 'webp':
                    imagewebp($cropped_image, $file_path, 90);
                    break;
                default:
                    throw new Exception("Unsupported image format.");
            }

            // Free memory
            imagedestroy($image);
            imagedestroy($cropped_image);

            return true;

        } catch (Exception $e) {
            error_log("⚠️ Image Cropping Error: " . $e->getMessage());
            return false;
        }
    }

    public static function save_raw_image($image)
    {
        $save_path = growtype_art_get_upload_dir($image['folder']) . '/' . $image['name'] . '.' . $image['extension']; // Save as JPG by default

        // Ensure folder exists
        if (!file_exists(dirname($save_path))) {
            mkdir(dirname($save_path), 0755, true);
        }

        // Save binary image data
        if (file_put_contents($save_path, $image['content'])) {
            return [
                'path' => $save_path,
                'url' => growtype_art_build_public_image_url($image)
            ];
        }

        return false;
    }

    private static function decode_base64_image($base64_string)
    {
        // Attempt to extract MIME type if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $matches)) {
            $extension = $matches[1];
            $base64_data = substr($base64_string, strpos($base64_string, ',') + 1);
        } else {
            // Assume raw b64_json without prefix
            $base64_data = $base64_string;
            $image_info = getimagesizefromstring(base64_decode($base64_data));
            if (!$image_info) {
                return ['is_valid' => false];
            }

            // Get MIME type and deduce extension
            $mime = $image_info['mime']; // e.g. image/png
            $extension = explode('/', $mime)[1];
        }

        // Decode base64 data
        $decoded_data = base64_decode($base64_data);
        if ($decoded_data === false) {
            return ['is_valid' => false];
        }

        // Save to temporary file
        $tmp_file = tempnam(sys_get_temp_dir(), 'img_') . '.' . $extension;
        file_put_contents($tmp_file, $decoded_data);

        // Get image size
        $dimensions = getimagesize($tmp_file);
        if (!$dimensions) {
            return ['is_valid' => false];
        }

        return [
            'is_valid' => true,
            'tmp_file' => $tmp_file,
            'extension' => $extension,
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'mime' => $dimensions['mime'] ?? null,
        ];
    }

    public static function is_valid_base64($base64String)
    {
        $base64String = preg_replace('/^data:image\/(png|jpe?g|gif|bmp|webp|svg+xml);base64,/', '', $base64String);
        return base64_decode($base64String, true) !== false;
    }

    public static function validate_file($url)
    {
        $wp_response = wp_remote_head($url, [
            'timeout'   => 10,
            'sslverify' => true,
            'redirection' => 5,
        ]);

        if (is_wp_error($wp_response)) {
            return [
                'is_valid' => false,
                'message'  => $wp_response->get_error_message(),
            ];
        }

        $contentType   = wp_remote_retrieve_header($wp_response, 'content-type');
        $contentLength = (int) wp_remote_retrieve_header($wp_response, 'content-length');

        if (!$contentType || $contentLength <= 0) {
            return [
                'is_valid' => false,
                'message' => 'File does not exist or headers missing.'
            ];
        }

        // Validate file size
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($contentLength > $maxSize) {
            return [
                'is_valid' => false,
                'message' => 'File size exceeds 10MB.'
            ];
        }

        // Validate MIME type
        $validMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
        ];

        if (!in_array($contentType, $validMimeTypes)) {
            return [
                'is_valid' => false,
                'message' => "Invalid file type: $contentType"
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'File is valid.'
        ];
    }

    public static function generate_seed($length = 11)
    {
        $number = '';
        for ($i = 0; $i < $length; $i++) {
            // Generate a random digit between 0 and 9
            $number .= mt_rand(0, 9);
        }
        return $number;
    }

    public static function get_providers_with_models()
    {
        $providers = self::API_GENERATE_IMAGE_PROVIDERS;
        $data = [];

        foreach ($providers as $provider) {
            $provider_class_name = sprintf('\partials\%s_Base', ucfirst($provider));

            if (class_exists($provider_class_name)) {
                $crud = new $provider_class_name();
                $models = $crud->get_models();

                if (!empty($models)) {
                    $data[$provider] = $models;
                }
            }
        }

        return $data;
    }

    /**
     * Returns all text-generation providers with their available models.
     *
     * Each entry: [ 'provider_key' => [ 'label' => '...', 'models' => [ 'model-id' => 'Model Label', ... ] ] ]
     *
     * Discovery rules (same pattern as get_providers_with_models):
     *   1. Try namespaced class  \partials\{Provider}_Base
     *   2. Fall back to plain    {Provider}_Base  (e.g. Openai_Base)
     *   3. Class must implement  get_text_models(): array
     *
     * Extensible via the `growtype_art_text_providers` filter.
     */
    public static function get_text_providers_with_models(): array
    {
        $providers = apply_filters('growtype_art_text_providers', self::API_GENERATE_TEXT_PROVIDERS);
        $data      = [];

        foreach ($providers as $provider) {
            $namespaced = sprintf('\partials\%s_Base', ucfirst($provider));
            $plain      = sprintf('%s_Base',           ucfirst($provider));

            if (class_exists($namespaced)) {
                $instance = new $namespaced();
            } elseif (class_exists($plain)) {
                $instance = new $plain();
            } else {
                continue;
            }

            if (!method_exists($instance, 'get_text_models')) {
                continue;
            }

            $models = $instance->get_text_models();

            if (empty($models)) {
                continue;
            }

            $label = method_exists($instance, 'get_provider_label')
                ? $instance->get_provider_label()
                : ucfirst($provider);

            $data[$provider] = [
                'label'  => $label,
                'models' => $models,
            ];
        }

        return $data;
    }

    /**
     * Returns all image-generation providers with their available models.
     * Mirrors get_text_providers_with_models() but uses API_GENERATE_IMAGE_PROVIDERS
     * and the existing get_models() method on each Base class.
     */
    public static function get_image_providers_with_models(): array
    {
        $providers = apply_filters('growtype_art_image_providers', self::API_GENERATE_IMAGE_PROVIDERS);
        $data      = [];

        foreach ($providers as $provider) {
            $namespaced = sprintf('\partials\%s_Base', ucfirst($provider));
            $plain      = sprintf('%s_Base',           ucfirst($provider));

            if (class_exists($namespaced)) {
                $instance = new $namespaced();
            } elseif (class_exists($plain)) {
                $instance = new $plain();
            } else {
                continue;
            }

            $models = method_exists($instance, 'get_models') ? $instance->get_models() : [];

            // Normalize: get_models() returns ['model-id' => [...meta]] or ['model-id' => 'label'].
            // Preserve the fields needed by the generator UI.
            $flat = [];
            foreach ($models as $model_id => $meta) {
                $label = is_array($meta) ? ($meta['label'] ?? ucwords(str_replace(['-', '_'], ' ', $model_id))) : (string)$meta;
                $ref   = is_array($meta) ? !empty($meta['supports_reference_image']) : false;
                $cost  = is_array($meta) && array_key_exists('cost_usd', $meta) && $meta['cost_usd'] !== null
                    ? (float) $meta['cost_usd']
                    : null;
                $cost_label = is_array($meta) ? ($meta['cost_label'] ?? null) : null;
                $flat[$model_id] = [
                    'label'      => $label,
                    'ref'        => $ref,
                    'cost_usd'   => $cost,
                    'cost_label' => $cost_label,
                ];
            }

            if (empty($flat)) {
                $flat = ['' => '— default —'];
            }

            $label = method_exists($instance, 'get_provider_label')
                ? $instance->get_provider_label()
                : ucfirst($provider);

            $data[$provider] = [
                'label'  => $label,
                'models' => $flat,
            ];
        }

        return $data;
    }

    /**
     * Returns all video-generation providers with their available models.
     * Same pattern as get_image_providers_with_models() but for API_GENERATE_VIDEO_PROVIDERS.
     */
    public static function get_video_providers_with_models(): array
    {
        $providers = apply_filters('growtype_art_video_providers', self::API_GENERATE_VIDEO_PROVIDERS);
        $data      = [];

        foreach ($providers as $provider) {
            $namespaced = sprintf('\partials\%s_Base', ucfirst($provider));
            $plain      = sprintf('%s_Base',           ucfirst($provider));

            if (class_exists($namespaced)) {
                $instance = new $namespaced();
            } elseif (class_exists($plain)) {
                $instance = new $plain();
            } else {
                continue;
            }

            $models = method_exists($instance, 'get_video_models') ? $instance->get_video_models()
                    : (method_exists($instance, 'get_models')      ? $instance->get_models() : []);

            $flat = [];
            foreach ($models as $model_id => $meta) {
                $flat[$model_id] = is_array($meta) ? ($meta['label'] ?? ucwords(str_replace(['-', '_'], ' ', $model_id))) : (string)$meta;
            }

            if (empty($flat)) {
                $flat = ['' => '— default —'];
            }

            $label = method_exists($instance, 'get_provider_label')
                ? $instance->get_provider_label()
                : ucfirst($provider);

            $data[$provider] = [
                'label'  => $label,
                'models' => $flat,
            ];
        }

        return $data;
    }
}
