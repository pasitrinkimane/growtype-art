<?php

use partials\Leonardoai_Base;

defined('ABSPATH') || exit;

class Growtype_Art_Admin_Model_List_Table_Record
{
    private $redirect;

    public function __construct()
    {
        $this->redirect = admin_url('edit.php?post_type=' . Growtype_Art_Admin::POST_TYPE . '&page=' . Growtype_Art_Admin::MODELS_PAGE_NAME);
    }

    /**
     * @return void
     */
    public function process_delete_action()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Art_Admin::MODELS_PAGE_NAME && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'delete' || $_REQUEST['action'] === 'bulk_delete')) {
            $action = $_REQUEST['action'];
            $action2 = $_REQUEST['action2'] ?? '';

            $model_id = isset($_REQUEST['model_id']) ? $_REQUEST['model_id'] : null;

            if (!empty($model_id)) {
                if ('delete' === $action || 'delete' === $action2) {
                    $nonce = esc_attr($_REQUEST['_wpnonce']);

                    if (!wp_verify_nonce($nonce, Growtype_Art_Admin::DELETE_NONCE)) {
                        die('Go get a life script kiddies');
                    } else {
                        self::delete_item(absint($model_id));
                    }
                }

                if ($action === 'bulk_delete' || $action2 === 'bulk_delete') {
                    $delete_ids = esc_sql($model_id);

                    if (!empty($delete_ids)) {
                        foreach ($delete_ids as $id) {
                            self::delete_item($id);
                        }
                    }
                }
            }

            do_action('growtype_art_model_delete', $model_id);

            $this->redirect_index();
        }
    }

    /**
     * @return void
     */
    public function process_add_new_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'create-model') {
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODELS_TABLE, [
                'prompt' => 'Sunset mountain',
                'negative_prompt' => 'Palm tree, beach, Sea',
                'reference_id' => growtype_art_generate_reference_id(),
                'provider' => get_option('growtype_art_images_saving_location'),
                'image_folder' => ''
            ]);

            $this->redirect_index();
        }
    }

    /**
     * @return void
     */
    public function process_bundle_action()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Art_Admin::MODELS_PAGE_NAME && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'add-to-bundle' || $_REQUEST['action'] === 'remove-from-bundle')) {
            $models_id = $_REQUEST['model_id'];

            if ($_REQUEST['action'] === 'add-to-bundle') {
                growtype_art_admin_update_bundle_keys($models_id, 'add');
            }

            if ($_REQUEST['action'] === 'remove-from-bundle') {
                growtype_art_admin_update_bundle_keys($models_id, 'remove');
            }

            $this->redirect_index();
        }
    }

    /**
     * @return void
     */
    public function process_download_cloudinary_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'index-download-model-images' || $_GET['action'] === 'index-download-all-models-images')) {

            d('This method is deprecated.');

            if ($_GET['action'] === 'index-download-model-images') {
                Growtype_Cron_Jobs::create_if_not_exists('download-cloudinary-folder', json_encode([
                    'model_id' => $_GET['item']
                ]), 30);
            } elseif ($_GET['action'] === 'index-download-all-models-images') {
                $models = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE, [
                    [
                        'orderby' => 'created_at',
                        'order' => 'asc',
                        'limit' => '400',
                        'offset' => 0,
                    ]
                ]);

                $seconds = 10;
                foreach ($models as $model) {

                    if ($model['id'] < 300 || $model['id'] > 350) {
                        continue;
                    }

                    Growtype_Cron_Jobs::create_if_not_exists('download-cloudinary-folder', json_encode([
                        'model_id' => $model['id']
                    ]), 30);

                    $seconds = $seconds + 10;
                }
            }

            $redirect = add_query_arg(
                'action',
                $_GET['action'],
                get_admin_url() . 'admin.php?' . sprintf('page=%s&paged=%s', $_REQUEST['page'], $_REQUEST['paged'])
            );

            wp_redirect($redirect);

            exit();
        }
    }

    /**
     * @return void
     */
    public function process_download_zip_action()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Art_Admin::MODELS_PAGE_NAME && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'download-zip')) {
            $models_ids = $_REQUEST['model_id'];

            foreach ($models_ids as $model_id) {
                echo '<iframe src="/wp/wp-admin/admin.php?page=growtype-art-models&action=download-ziped-model&model_id=' . $model_id . '"></iframe>';
            }

            echo '<script>setTimeout(function (){window.location.href = "/wp/wp-admin/admin.php?page=growtype-art-models&paged=' . $_REQUEST['paged'] . '";},3000)</script>';

            exit();
        }

        if (isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Art_Admin::MODELS_PAGE_NAME && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'download-ziped-model')) {
            $model_id = $_REQUEST['model_id'];
            $this->download_ziped_model($model_id);
        }
    }

    public function download_ziped_model($model_id)
    {
        $model_details = growtype_art_get_model_details($model_id);

        if (empty($model_details)) {
            $this->redirect_index();
        }

        $image_folder = $model_details['image_folder'];
        $image_folder_path = growtype_art_get_upload_dir($image_folder);
        $files = scandir($image_folder_path);
        $zipFile = 'model_id_' . $model_id . '.zip';
        $zip = new ZipArchive;
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $zip->addFile($image_folder_path . '/' . $file, $file);
        }

        $zip->close();

        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename = $zipFile");
        header('Content-Length: ' . filesize($zipFile));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zipFile");
    }

    /**
     * @return void
     */
    public function process_update_action()
    {
        $model_id = $_POST['update_item_id'] ?? '';

        if (!empty($model_id)) {
            $_POST = stripslashes_deep($_POST);
            $model_details = $_POST['model'];
            $model_details['provider'] = $model_details['provider'][0];

            $settings = $_POST['settings'];

            foreach ($settings as $key => $setting) {
                if (is_array($setting)) {
                    $settings[$key] = json_encode($setting);
                }
            }

            $values_to_encode = [
                'tags',
                'face_swap_photos',
            ];

            foreach ($values_to_encode as $value_to_encode) {
                if (isset($settings[$value_to_encode]) && !empty($settings[$value_to_encode])) {
                    $settings[$value_to_encode] = json_encode(explode(',', $settings[$value_to_encode]));
                }
            }

            if (isset($settings['slug']) && !empty($settings['slug'])) {
                $settings['slug'] = growtype_art_format_character_slug($settings['slug'], $model_id);
            }

            /**
             * Set default value
             */
            if (!isset($settings['faceswap_new_uploads'])) {
                $settings['faceswap_new_uploads'] = 0;
            }

            if (!isset($settings['auto_check_for_nsfw'])) {
                $settings['auto_check_for_nsfw'] = 0;
            }

            if (isset($settings['data_for_generating_character_details']) && empty($settings['data_for_generating_character_details'])) {
                unset($settings['data_for_generating_character_details']);
            }

            /**
             * Save custom assets
             */
            if (isset($_FILES['custom_assets']) && !empty($_FILES['custom_assets'])) {
                $custom_assets = $_FILES['custom_assets'];

                $separated_files = [];
                foreach ($custom_assets as $key => $file_data) {
                    foreach ($file_data as $index => $value) {
                        $separated_files[$index][$key] = $value;
                    }
                }

                $model_images = growtype_art_get_model_images_grouped($model_id, 1000)['original'];

                foreach ($separated_files as $file) {
                    if (empty($file['name'])) {
                        continue;
                    }

                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

                    $file['folder'] = $model_details['image_folder'];
                    $saved_image = Growtype_Art_Crud::save_image($file);

                    if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                        error_log('save_generations: ' . json_encode($saved_image));
                        continue;
                    }

                    Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, ['model_id' => $model_id, 'image_id' => $saved_image['id']]);

                    if (in_array($file_extension, ['mp4'])) {
                        foreach ($model_images as $model_image) {
                            $model_image_basename = $model_image['name'];
                            $model_image_extension = $model_image['extension'];
                            $model_image_basename = explode('-', $model_image_basename)[0];

                            if ($model_image_extension === $file_extension) {
                                continue;
                            }

                            $file_basename = pathinfo($file['name'], PATHINFO_FILENAME);
                            $file_basename = explode('-', $file_basename)[0];

                            if ($model_image_basename === $file_basename) {
                                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $model_image['id'],
                                    'meta_key' => 'video_url_image_id_' . $saved_image['id'],
                                    'meta_value' => $saved_image['details']['url'],
                                ]);

                                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $saved_image['id'],
                                    'meta_key' => 'parent_image_id',
                                    'meta_value' => $model_image['id'],
                                ]);

                                break;
                            }
                        }
                    }
                }
            }

            Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, $model_details, $model_id);

            Growtype_Art_Database_Crud::update_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE,
                [
                    [
                        'key' => 'model_id',
                        'values' => [$model_id]
                    ]
                ],
                [
                    'reference_key' => 'meta_key',
                    'update_value' => 'meta_value'
                ],
                $settings
            );

            $data_for_generating_character_details = $settings['data_for_generating_character_details'] ?? '';
            $data_for_generating_character_details = !empty($data_for_generating_character_details) ? json_decode($data_for_generating_character_details, true) : '';

            if (!empty($data_for_generating_character_details)) {
                Openai_Base_Image::update_character_details($model_id, $data_for_generating_character_details);
            }

            do_action('growtype_art_model_update', $model_id);
        }
    }

    /**
     * @return void
     */
    public function process_duplicate_model_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'index-duplicate-model') {

            growtype_art_admin_duplicate_model($_GET['model']);

            /**
             * Redirect
             */
            $redirect_args = [
                'message_type' => 'custom',
                'message' => __('Model duplicated', 'growtype-art'),
            ];

            $this->redirect_index($redirect_args);
        }
    }

    /**
     * @return void
     */
    public function process_sync_model_images_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'pull-model-images') {
            $crud = new Cloudinary_Crud();
            $crud->pull_images($_GET['item']);

            wp_redirect(get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&action=%s&item=%s', Growtype_Art_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $_GET['item']));

            exit();
        }
    }

    /**
     * @return void
     */
    public function process_generate_image_action()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : null;
        $model_id = isset($_GET['model']) ? $_GET['model'] : null;

        if (!empty($action) && !empty($model_id)) {
            switch ($action) {
                case 'generate-images':
                    $generate_details = $this->generate_model_image($model_id);

                    $redirect_args = [
                        'message_type' => 'custom',
                    ];

                    if ($generate_details['success'] === false) {
                        $redirect_args['message'] = $generate_details['message'] ?? __('Something went wrong', 'growtype-art');
                        $redirect_args['status'] = 'error';
                    } else {
                        $redirect_args['message'] = $generate_details['message'] ?? sprintf(__('%d image is generating. User nr: %s. Image prompt: %s', 'growtype-art'), 1, $generate_details['user_nr'] ?? '-', $generate_details['image_prompt'] ?? '');
                    }

                    $this->redirect_single($redirect_args);
                    break;
                case 'index-generate-images':
                    $generate_details = $this->generate_model_image($model_id);

                    $redirect_args = [
                        'message_type' => 'custom',
                        'message' => $generate_details['message'] ?? sprintf(__('%d image is generating. User nr: %s. Image prompt: %s', 'growtype-art'), 1, $generate_details['user_nr'] ?? '-', $generate_details['image_prompt'] ?? ''),
                    ];

                    $this->redirect_index($redirect_args);
                    break;
                case 'faceswap-images':
                    Retrieve_Faceswap_Image_Job::initiate($model_id);

                    $this->redirect_single();
                    break;
            }
        }
    }

    public function generate_model_image($model_id)
    {
        $provider = growtype_art_get_model_details($model_id)['provider'] ?? '';

        if (empty($provider)) {
            error_log(sprintf('Provider not found for model id: %s', $model_id));

            return [
                'success' => false,
                'message' => 'Provider not found.'
            ];
        }

        if ($provider === 'writecream') {
            $provider = 'runware';
        }

        $provider_class_name = sprintf('\partials\%s_Base', ucfirst($provider));

        if ($provider === Growtype_Art_Crud::LEONARDOAI_KEY) {
            $crud = new \partials\Leonardoai_Base();
            $generate_details = $crud->generate_model_image($model_id);
        } elseif (class_exists($provider_class_name)) {
            $crud = new $provider_class_name();
            $generate_details = $crud->generate_model_image($model_id);
        }

        return $generate_details ?? [];
    }

    public function generate_image($prompt, $provider)
    {
        $provider_class_name = sprintf('\partials\%s_Base', ucfirst($provider));

        $crud = new $provider_class_name();
        $generate_details = $crud->generate_image_init([
            'prompt' => $prompt,
        ]);

        return $generate_details ?? [];
    }

    /**
     * @return void
     */
    public function process_generate_content_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'generate-model-content' || $_GET['action'] === 'generate-image-content' || $_GET['action'] === 'regenerate-image-content')) {
            $openai_crud = new Openai_Base_Image();

            $model_id = isset($_GET['model']) ? $_GET['model'] : null;

            if (!empty($model_id)) {
                if ($_GET['action'] === 'generate-model-content') {
                    $openai_crud->format_models(null, false, $_GET['model']);
                } else {
                    if ((isset($_GET['image']) && !empty($_GET['image']))) {
                        $openai_crud->format_image($_GET['image'], true);
                    } else {
                        $regenerate = $_GET['action'] === 'regenerate-image-content';
                        $openai_crud->format_model_images($_GET['model'], $regenerate);
                    }
                }
            }

            $this->redirect_single();
        }

        if (isset($_GET['action']) && ($_GET['action'] === 'generate-model-character-content')) {
            $openai_crud = new Openai_Base_Image();

            $model_id = isset($_GET['model']) ? $_GET['model'] : null;

            if (!empty($model_id)) {
                $openai_crud->format_model_character($model_id);
            }

            $this->redirect_single();
        }
    }

    /**
     * @return void
     */
    public function process_retrieve_action()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : null;
        $model_id = isset($_GET['model']) ? $_GET['model'] : null;

        if (!empty($action)) {
            switch ($action) {
                case 'retrieve-model-images':
                    $crud = new Leonardoai_Base();
                    $amount = 100;

                    if (empty($model_id)) {
                        $this->redirect_single([
                            'message_type' => 'custom',
                            'message' => __('Model is misssing.', 'growtype-art'),
                        ]);
                    }

                    $leonardoai_settings_user_nr = growtype_art_get_model_single_setting($model_id, 'leonardoai_settings_user_nr');
                    $leonardoai_settings_user_nr = $leonardoai_settings_user_nr['meta_value'] ?? '';

                    $users_credentials = Leonardoai_Base::user_credentials();

                    if (!empty($leonardoai_settings_user_nr)) {
                        $updated_users_credentials = [];
                        $updated_users_credentials[$leonardoai_settings_user_nr] = $users_credentials[$leonardoai_settings_user_nr];
                        $users_credentials = $updated_users_credentials;
                    }

                    foreach ($users_credentials as $user_nr => $user_credentials) {

                        if (empty($user_credentials['user_id'])) {
                            continue;
                        }

                        $generations = $crud->retrieve_models($amount, $model_id, $user_nr);

                        if (empty($generations)) {
                            continue;
                        }
                    }

                    $this->redirect_single([
                        'message_type' => 'custom',
                        'message' => sprintf(__('%d images are retrieving.', 'growtype-art'), $amount),
                    ]);
                    break;
                case 'retrieve-models':
                    $crud = new Leonardoai_Base();
                    $amount = 5;
                    $users_credentials = Leonardoai_Base::user_credentials();

                    $retrieved_users = [];
                    foreach ($users_credentials as $user_nr => $user_credentials) {
                        if (!isset($user_credentials['user_id']) || empty($user_credentials['user_id'])) {
                            continue;
                        }

                        array_push($retrieved_users, $user_credentials['user_id']);

                        $generations = $crud->retrieve_models($amount, null, $user_nr);

                        if (empty($generations)) {
                            continue;
                        }

                        sleep(1);
                    }

                    if (empty($retrieved_users)) {
                        $this->redirect_index([
                            'message_type' => 'custom',
                            'message' => __('No users retrieved. Please enter users credentials in settings.', 'growtype-art'),
                            'status' => 'error',
                        ]);
                    } else {
                        $this->redirect_index([
                            'message_type' => 'custom',
                            'message' => sprintf(__('Users amount retrieved: %d', 'growtype-art'), count($retrieved_users)),
                        ]);
                    }

                    break;
                case 'generate-models':
                    if (isset($_POST['models-to-generate']) && !empty($_POST['models-to-generate'])) {
                        $models_to_generate = explode(",", trim($_POST['models-to-generate']));
                        $models_to_generate = array_map('trim', $models_to_generate);
                        $provider = $_POST['model']['provider'][0] ?? '';
                        $style = $_POST['model']['style'][0] ?? '';

                        $info_message = '';

                        foreach ($models_to_generate as $model_to_generate) {
                            $model_slug = growtype_art_format_character_slug($model_to_generate);

                            if (strlen($model_to_generate) + 5 < strlen($model_slug)) {
                                $info_message .= sprintf(__('Model "%s" already exists.', 'growtype-art'), $model_to_generate) . " | ";
                                continue;
                            }

                            $character_details = growtype_art_generate_character_details($model_to_generate);

                            if (empty($character_details)) {
                                $info_message .= sprintf(__('Model "%s" GPT details are empty.', 'growtype-art'), $model_to_generate) . "\r\n";
                                continue;
                            }

                            $character_details['character_title'] = ucwords(strtolower($character_details['character_title']));

                            $new_model_id = growtype_art_admin_duplicate_model(growtype_art_default_model_id_to_duplicate());

                            Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                                'prompt' => '((({character_style} style))) Generate ((full body)) image in {character_style} style of {character_title} {character_age} years old {character_ethnicity} {character_gender} {character_occupation}, {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, natural lighting, 35mm, f/2, 8K. Ensure visible skin texture for a {character_style} style portrayal. Utilize a {character_style} style with a 50mm lens for a balanced composition.',
                            ], $new_model_id);

                            if (!empty($provider)) {
                                Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                                    'provider' => $_POST['model']['provider'][0]
                                ], $new_model_id);
                            }

                            if (!empty($style)) {
                                $character_details['character_style'] = $style;
                                if ($style === 'realistic') {
                                    $character_details['categories'] = '{"People":{}}';
                                } else {
                                    $character_details['categories'] = '{"Anime & Manga":{}}';
                                }
                            }

                            Openai_Base_Image::update_character_details($new_model_id, $character_details);

                            growtype_art_admin_update_model_settings($new_model_id, [
                                'generatable_images_limit' => '5',
                                'slug' => growtype_art_format_character_slug($character_details['character_title'], $new_model_id),
                            ]);

                            growtype_art_admin_update_bundle_keys([$new_model_id], 'add');
                        }

                        if (!empty($info_message)) {
                            $this->redirect_index([
                                'message_type' => 'custom',
                                'message' => $info_message,
                                'status' => 'error',
                            ]);
                        }

                        $this->redirect_index([
                            'message_type' => 'custom',
                            'message' => __('Characters generated', 'growtype-art'),
                        ]);
                    }

                    ?>
                    <form method="post">
                        <h1>Generate models</h1>
                        <div>
                            <label>Provider</label>
                            <?= self::render_provider_select() ?>
                        </div>
                        <div>
                            <label>Style</label>
                            <?= self::render_select('name="model[style][]"', '', [
                                'realistic' => 'realistic',
                                'anime' => 'anime'
                            ]) ?>
                        </div>
                        <textarea id="models-to-generate" name="models-to-generate" rows="20" style="width: 100%;"></textarea>
                        <button type="submit" class="button button-primary" style="margin-top: 20px;">Generate</button>
                    </form>
                    <?php

                    exit();
            }
        }
    }

    /**
     * @return void
     */
    public function process_modify_images_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'upscale-images') {
            $model_id = isset($_GET['model']) ? $_GET['model'] : null;

            if (!empty($model_id)) {
                $model_images = growtype_art_get_model_images_grouped($model_id)['original'] ?? [];

                foreach ($model_images as $image) {
                    $image_id = $image['id'];

                    if (isset($image['settings']['real_esrgan'])) {
                        continue;
                    }

                    $optimization_type = 'local';

                    if ($optimization_type === 'external') {
                        $cloudinary_public_id = growtype_art_get_cloudinary_public_id($image);

                        $cloudinary = new Cloudinary_Crud();
                        $asset = $cloudinary->get_asset($cloudinary_public_id);

                        if (isset($asset['context']['custom']['real_esrgan'])) {
                            if (!isset($image['settings']['real_esrgan'])) {
                                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $image_id,
                                    'meta_key' => 'real_esrgan',
                                    'meta_value' => 'true',
                                ]);
                            }

                            if (!isset($image['settings']['compressed'])) {
                                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $image_id,
                                    'meta_key' => 'compressed',
                                    'meta_value' => 'true',
                                ]);
                            }

                            continue;
                        }

                        $upscale_image_url = $asset['url'];

                        $real_esrgan = new Replicate_Crud();
                        $real_esrgan->upscale($upscale_image_url, [
                            'id' => $image['id'],
                            'public_id' => $cloudinary_public_id
                        ]);
                    } else {
                        Growtype_Cron_Jobs::create_if_not_exists('upscale-image', json_encode([
                            'image_id' => $image['id'],
                        ]), 5);
                    }
                }
            }

            $this->redirect_single();
        }
    }

    public function delete_item($id)
    {
        if (empty($id)) {
            return;
        }

        growtype_art_admin_update_bundle_keys([$id], 'remove');

        $model = growtype_art_get_model_details($id);

        $images_saving_location = growtype_art_get_images_saving_location();

        if ($images_saving_location === 'cloudinary') {
            $crud = new Cloudinary_Crud();

            if (!empty($model['image_folder'])) {
                $crud->delete_folder_with_assets($model['image_folder']);
            }
        }

        $images = growtype_art_get_model_images_grouped($id)['original'] ?? [];

        /**
         * Remove images
         */
        if (!empty($images)) {
            foreach ($images as $image) {
                $img_path = growtype_art_get_image_path($image['id']);

                if (!empty($img_path)) {
                    unlink(growtype_art_get_image_path($image['id']));
                }
            }
        }

        if (!empty($model['image_folder'])) {
            $model_images_dir = growtype_art_get_upload_dir() . '/' . $model['image_folder'];

            if (file_exists($model_images_dir)) {
                rmdir($model_images_dir);
            }
        }

        return Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::MODELS_TABLE, [$id]);
    }

    public function redirect_single($args = [])
    {
        $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?page=%s&action=%s&model=%s', $_REQUEST['page'], 'edit', $_GET['model']);

        $redirect = add_query_arg(
            $args,
            $redirect_url
        );

        wp_redirect($redirect);

        exit();
    }

    public function redirect_index($args = [])
    {
        $paged = isset($_GET['paged']) ? $_GET['paged'] : 1;

        $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?page=%s&paged=%s', $_REQUEST['page'], $paged);

        $redirect = add_query_arg(
            $args,
            $redirect_url
        );

        wp_redirect($redirect);

        exit();
    }

    /**
     * @return void
     */
    public function process_delete_image()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'delete-image') {

            $image_id = isset($_GET['image']) ? $_GET['image'] : null;

            if (!empty($image_id)) {
                Growtype_Art_Crud::delete_image($image_id);
            }

            $this->redirect_single();
        }
    }

    /**
     * @return void
     */
    public function process_update_model_images_colors()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'update-model-images-colors') {

            $images = growtype_art_get_model_images_grouped($_GET['model'])['original'] ?? [];

            foreach ($images as $image) {
                Growtype_Cron_Jobs::create_if_not_exists('extract-image-colors', json_encode([
                    'image_id' => $image['id'],
                    'update_colors' => true
                ]), 1);
            }

            $this->redirect_single();
        }
    }

    public function prepare_inner_table($id)
    {
        $model_details = growtype_art_get_model_details($id);

        $character_details = [];
        foreach ($model_details['settings'] as $key => $model_setting) {
            if (strpos($key, 'character_') === false || $key === 'data_for_generating_character_details') {
                continue;
            }
            $character_details[$key] = $model_setting;
        }

        $models_settings = [];
        foreach ($model_details['settings'] as $key => $model_setting) {
            if (strpos($key, 'character_') !== false) {
                continue;
            }
            $models_settings[$key] = $model_setting;
        }

        $model_slug = $models_settings['slug'];
        $featured_in = $models_settings['featured_in'];

        $model_settings_collected_keys = [
            'tags',
            'slug',
            'prompt_variables',
            'featured_in',
            'categories',
            'model_is_private',
            'created_by',
            'generatable_images_limit',
            'leonardoai_settings_user_nr',
            'face_swap_photos',
            'faceswap_new_uploads',
            'priority_level',
            'auto_check_for_nsfw',
            'created_by_unique_hash',
            'post_type_to_collect_data_from',
            'title',
            'description',
            'init_generation_image_id',
        ];

        $prioritized_keys = ['slug', 'featured_in'];
        $models_settings_reordered = [];

        foreach ($prioritized_keys as $key) {
            if (isset($models_settings[$key])) {
                $models_settings_reordered[$key] = $models_settings[$key];
                unset($models_settings[$key]); // Remove to avoid duplication
            }
        }

        $models_settings_reordered += $models_settings;

        if (!empty($model_slug)) {
            $featured_in_values = json_decode($featured_in, true);
            $featured_in_values = !empty($featured_in_values) ? $featured_in_values : [];

            foreach ($featured_in_values as $featured_in_value) {
                $profile_url = sprintf('https://' . $featured_in_value . '.com/profile/%s', $model_slug);
                echo '<a href="' . $profile_url . '" target="_blank">' . $featured_in_value . ' profile: ' . $profile_url . '</a>';
            }
        }

        ?>

        <div class="tab">
            <div class="tab-header">
                <h2>Model settings</h2>
            </div>
            <div class="tab-content">
                <table class="form-table">
                    <tbody>
                    <?php foreach ($model_details as $key => $item) {
                        if (in_array($key, [
                            'id',
                            'created_at',
                            'updated_at',
                            'settings'
                        ])) {
                            continue;
                        }

                        echo self::render_table_row($key, $item, 'model');
                    } ?>

                    <?php foreach ($models_settings_reordered as $key => $item) {
                        if (!in_array($key, $model_settings_collected_keys)) {
                            continue;
                        }

                        echo self::render_table_row($key, $item, 'settings');
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab">
            <div class="tab-header">
                <h2>Generating settings</h2>
            </div>
            <div class="tab-content">
                <table class="form-table">
                    <tbody>
                    <?php foreach ($models_settings as $key => $item) {
                        if (in_array($key, $model_settings_collected_keys)) {
                            continue;
                        }

                        echo self::render_table_row($key, $item, 'settings');
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab">
            <div class="tab-header">
                <h2>Character details</h2>
            </div>
            <div class="tab-content">
                <table class="form-table">
                    <tbody>
                    <?php
                    foreach ($character_details as $key => $item) {
                        if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                            continue;
                        }

                        ?>
                        <tr>
                            <th scope="row">
                                <label for=""><?php echo $key ?></label>
                            </th>
                            <td>
                                <?php if (in_array($key, [
                                    'character_description',
                                    'character_introduction',
                                    'character_gpt_personality_extension',
                                    'character_intro_message',
                                    'character_can_answer_to_questions',
                                    'character_popular_topics_to_discuss',
                                    'character_dreams',
                                ])) { ?>
                                    <textarea class="large-text code" rows="5" name="settings[<?php echo $key ?>]"><?php echo $item ?></textarea>
                                <?php } else { ?>
                                    <input class="regular-text code" type="text" name="settings[<?php echo $key ?>]" value="<?php echo $item ?>"/>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <hr>

                <h3>Message for generating character details:</h3>
                <?php
                $data_for_generating = Openai_Base_Image::get_model_data_for_generating($id);
                $data_for_generating = Openai_Base_Image::message_for_generating_character_description($data_for_generating);

                echo $data_for_generating;
                ?>

                <h3>Last data for generating character details:</h3>
                <?php
                $character_generating_details = growtype_art_get_model_single_setting($id, 'data_for_generating_character_details')['meta_value'] ?? '';
                echo $character_generating_details;
                ?>

                <h3>Data for generating character details:</h3>
                <textarea class="large-text code" rows="5" name="settings[data_for_generating_character_details]"></textarea>
            </div>
        </div>

        <div style="margin-bottom: 30px;">
            <p>Upload custom assets</p>
            <input id="custom_assets" type="file" name="custom_assets[]" multiple="multiple">
        </div>

        <!--        ---------->

        <input type="hidden" name="update_item_id" value="<?php echo $id ?>">

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>

        <hr>
        <hr>

        <div style="display:flex;justify-content: flex-end;margin-bottom: 40px;">
            <div style="display: flex;flex-wrap: wrap;gap:10px;flex-direction: column;width: 100%;">
                <div>
                    <p>Generating</p>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Generate image', 'growtype-art') . '</a>', $_REQUEST['page'], 'generate-images', $id) ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Face swap images', 'growtype-art') . '</a>', $_REQUEST['page'], 'faceswap-images', $id) ?>
                </div>
                <div>
                    <p>Content</p>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Generate model details', 'growtype-art') . '</a>', $_REQUEST['page'], 'generate-model-content', $id) ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Generate character details', 'growtype-art') . '</a>', $_REQUEST['page'], 'generate-model-character-content', $id) ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Generate images details', 'growtype-art') . '</a>', $_REQUEST['page'], 'generate-image-content', $id) ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Re-Generate images details', 'growtype-art') . '</a>', $_REQUEST['page'], 'regenerate-image-content', $id) ?>
                </div>
                <div>
                    <p>Retrieving</p>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Pull images', 'growtype-art') . '</a>', $_REQUEST['page'], 'pull-model-images', $id) ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Retrieve images', 'growtype-art') . '</a>', $_REQUEST['page'], 'retrieve-model-images', $id) ?>
                </div>
                <div>
                    <p>Images Modification</p>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Update colors', 'growtype-art') . '</a>', $_REQUEST['page'], 'update-model-images-colors', $id) ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-secondary">' . __('Upscale images', 'growtype-art') . '</a>', $_REQUEST['page'], 'upscale-images', $id) ?>
                </div>
            </div>
        </div>

        <script>
            $ = jQuery;

            /**
             * Category select
             */

            window.categorySelect = '<?php echo $model_details['settings']['categories'] ?>';

            window.categorySelect = window.categorySelect ? JSON.parse(window.categorySelect) : {}

            Object.entries(window.categorySelect).forEach(([key, value]) => {
                $('.category-select input[data-value="' + key + '"]').prop('checked', true)

                Object.entries(value).forEach(([key, value]) => {
                    $('.category-select input[data-value="' + key + '"]').prop('checked', true)
                })
            })

            $('input[name="settings[categories]"]').val(JSON.stringify(window.categorySelect))

            $('.category-select input[type="checkbox"]').click(function () {
                window.categorySelect = {}

                $('.category-select > li').each(function (index, element) {
                    let parent = $(element).find('input[type="checkbox"]:checked').attr('data-value')

                    if (parent) {
                        window.categorySelect[parent] = {}

                        $(element).find('li').each(function (index, element) {
                            let child = $(element).find('input[type="checkbox"]:checked').attr('data-value')

                            if (child) {
                                window.categorySelect[parent][child] = {}
                            }
                        })
                    }
                })

                $('input[name="settings[categories]"]').val(JSON.stringify(window.categorySelect))
            });
        </script>

        <?php
    }

    public static function render_table_row($meta_key, $meta_value, $name)
    {
        if (in_array($meta_key, ['tags', 'face_swap_photos']) && !empty(json_decode($meta_value))) {
            $meta_value = implode(',', json_decode($meta_value));
        }

        if (in_array($meta_key, ['tags'])) {
            $meta_value = strtolower($meta_value);
        }

        if ($meta_key === 'generatable_images_limit' && empty($meta_value)) {
            $meta_value = '30';
        }

        if ($meta_key === 'slug' && empty($meta_value)) {
            $meta_value = strtolower(wp_generate_password(12, false));
        }
        ?>
        <tr>
            <th scope="row">
                <label for=""><?php echo $meta_key ?></label>
                <?php
                if ($meta_key === 'prompt') {
                    echo '<p class="text">Use <code>{prompt_variables}</code> to insert random prompt variable</p>';
                }
                ?>
            </th>
            <td>
                <?php if (in_array($meta_key, ['prompt', 'negative_prompt', 'description', 'tags', 'face_swap_photos', 'prompt_variables', 'image_prompts'])) { ?>
                    <textarea class="large-text code" rows="5" name="<?php echo $name ?>[<?php echo $meta_key ?>]"><?php echo $meta_value ?></textarea>
                <?php } elseif (in_array($meta_key, ['faceswap_new_uploads', 'auto_check_for_nsfw'])) { ?>
                    <input class="checkbox" type="checkbox" name="<?php echo $name ?>[<?php echo $meta_key ?>]" <?php echo checked(1, $meta_value, false) ?> value="1"/>
                <?php } elseif ($meta_key === 'featured_in') {
                    $meta_value = !empty($meta_value) ? json_decode($meta_value, true) : [];
                    echo self::render_featured_in_select($meta_value);
                } elseif ($meta_key === 'provider') {
                    echo self::render_provider_select($meta_value);
                } elseif ($meta_key === 'categories') { ?>
                    <ul class="category-select">
                        <?php
                        $art_categories = growtype_art_get_art_categories();
                        foreach ($art_categories as $category => $values) { ?>
                            <li>
                                <label><input type="checkbox" class="category" data-value="<?php echo $category ?>"><?php echo $category ?></label>
                                <ul style="margin-left: 30px;margin-top: 10px;margin-bottom: 10px;">
                                    <?php foreach ($values as $value) { ?>
                                        <li><label><input type="checkbox" class="category-child" data-value="<?php echo $category ?>_<?php echo $value ?>"><?php echo $value ?></label></li>
                                    <?php } ?>
                                </ul>
                            </li>
                        <?php } ?>
                    </ul>
                    <input type="hidden" name="<?php echo $name ?>[categories]" value=""/>
                <?php } else { ?>
                    <input class="regular-text code" type="text" name="<?php echo $name ?>[<?php echo $meta_key ?>]" value="<?php echo $meta_value ?>"/>
                <?php } ?>
            </td>
        </tr>
        <?php
    }

    public static function render_featured_in_select($value)
    {
        $options = growtype_art_get_model_featured_in_options();
        self::render_select('name="settings[featured_in][]"', $value, $options, true);
    }

    public static function render_provider_select($value = null)
    {
        $options = growtype_art_get_model_provider_options();
        echo self::render_select('name="model[provider][]"', $value, $options);
    }

    public static function render_textarea($name, $value)
    {
        echo '<textarea class="large-text code" rows="5" name="' . $name . '">' . $value . '</textarea>';
    }

    public static function render_select($name, $value, $options = [], $multiple = false)
    {
        $value = is_array($value) ? $value : [$value];
        ?>
        <select <?php echo $name ?> <?php echo $multiple ? 'multiple' : '' ?>>
            <option value="">-</option>
            <?php foreach ($options as $key => $option) { ?>
                <option value="<?php echo $key ?>" <?= selected(in_array($key, $value)) ?>><?php echo $option ?></option>
            <?php } ?>
        </select>
        <?php
    }
}

