<?php

class Growtype_Art_Crud
{
    const LEONARDOAI_KEY = 'leonardoai';
    const PICLUMEN_KEY = 'piclumen';
    const AIEASE_KEY = 'aiease';
    const RUNWARE_KEY = 'runware';
    const SEGMIND_KEY = 'segmind';
    const GEMINI_KEY = 'gemini';
    const POLLINATIONS_KEY = 'pollinations';
    const REPLICATE_KEY = 'replicate';
    const TOGETHERAI_KEY = 'togetherai';
    const FREEFLUX_KEY = 'freeflux';
    const PERCHANCE_KEY = 'perchance';
    const FLATAI_KEY = 'flatai';
    const WRITECREAM_KEY = 'writecream';

    const NSFW_PROVIDERS = [
        self::RUNWARE_KEY,
        self::POLLINATIONS_KEY,
        self::SEGMIND_KEY,
//        self::FREEFLUX_KEY,
//        self::WRITECREAM_KEY,
    ];

    const API_GENERATE_IMAGE_PROVIDERS = [
//        self::LEONARDOAI_KEY,
//        self::PICLUMEN_KEY,
//        self::AIEASE_KEY,
        self::RUNWARE_KEY,
        self::POLLINATIONS_KEY,
        self::SEGMIND_KEY,
//        self::TOGETHERAI_KEY,
//        self::FREEFLUX_KEY,
//        self::WRITECREAM_KEY,
    ];

    const API_GENERATE_VIDEO_PROVIDERS = [
        self::REPLICATE_KEY,
    ];

    const PROVIDERS_TO_INSTANTLY_GENERATE_IMAGES = [
        self::POLLINATIONS_KEY,
        self::RUNWARE_KEY,
        self::SEGMIND_KEY,
//        self::FREEFLUX_KEY,
//        self::WRITECREAM_KEY,
    ];

    const MODEL_GENERATE_IMAGE_PROVIDERS = [
        Growtype_Art_Crud::LEONARDOAI_KEY => Growtype_Art_Crud::LEONARDOAI_KEY,
        Growtype_Art_Crud::PICLUMEN_KEY => Growtype_Art_Crud::PICLUMEN_KEY,
        Growtype_Art_Crud::AIEASE_KEY => Growtype_Art_Crud::AIEASE_KEY,
        Growtype_Art_Crud::POLLINATIONS_KEY => Growtype_Art_Crud::POLLINATIONS_KEY,
        Growtype_Art_Crud::TOGETHERAI_KEY => Growtype_Art_Crud::TOGETHERAI_KEY,
//        Growtype_Art_Crud::FREEFLUX_KEY => Growtype_Art_Crud::FREEFLUX_KEY,
        Growtype_Art_Crud::RUNWARE_KEY => Growtype_Art_Crud::RUNWARE_KEY,
        Growtype_Art_Crud::GEMINI_KEY => Growtype_Art_Crud::GEMINI_KEY,
        Growtype_Art_Crud::SEGMIND_KEY => Growtype_Art_Crud::SEGMIND_KEY,
//        Growtype_Art_Crud::WRITECREAM_KEY => Growtype_Art_Crud::WRITECREAM_KEY,
    ];

    const IMAGES_FOLDER_NAME = 'models';

    const HTTP_HEADER = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36",
        "Accept: image/jpeg,image/png,image/gif,image/webp,*/*;q=0.8",
        "Connection: keep-alive"
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

            $extensions = ['webp', 'jpg', 'jpeg', 'png'];

            // Loop through each extension and attempt to delete the file
            foreach ($extensions as $extension) {
                $file_to_delete = $directory . DIRECTORY_SEPARATOR . $filename_without_extension . '.' . $extension;
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }
        }

        Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::IMAGES_TABLE, [$image_id]);
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

            error_log(sprintf('Saved image 2: %s', print_r($saved_image, true)));
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Set headers to mimic a real browser
        curl_setopt($ch, CURLOPT_HTTPHEADER, self::HTTP_HEADER);

        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);

        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);

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
}
