<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

class Growtype_Ai_Admin_Result_List_Table_Record
{
    public function __construct()
    {
        $this->redirect = admin_url('edit.php?post_type=' . Growtype_Ai_Admin::POST_TYPE . '&page=' . Growtype_Ai_Admin::MODELS_PAGE_NAME);
    }

    /**
     * @return void
     */
    public function process_delete_action()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Ai_Admin::MODELS_PAGE_NAME && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'delete' || $_REQUEST['action'] === 'bulk_delete')) {
            $action = $_REQUEST['action'];
            $action2 = $_REQUEST['action2'] ?? '';

            $model_id = isset($_REQUEST['model']) ? $_REQUEST['model'] : null;

            if (!empty($model_id)) {
                if ('delete' === $action || 'delete' === $action2) {
                    $nonce = esc_attr($_REQUEST['_wpnonce']);

                    if (!wp_verify_nonce($nonce, Growtype_Ai_Admin::DELETE_NONCE)) {
                        die('Go get a life script kiddies');
                    } else {
                        self::delete_item(absint($model_id));
                    }
                }

                if ($action === 'bulk_delete' || $action2 === 'bulk_delete') {
                    $delete_ids = esc_sql($model_id);

                    foreach ($delete_ids as $id) {
                        self::delete_item($id);
                    }
                }
            }

            $this->redirect_index();
        }
    }

    /**
     * @return void
     */
    public function process_add_new_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'create-model') {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODELS_TABLE, [
                'prompt' => 'Sunset mountain',
                'negative_prompt' => 'Palm tree, beach, Sea',
                'reference_id' => growtype_ai_generate_reference_id(),
                'provider' => get_option('growtype_ai_images_saving_location'),
                'image_folder' => ''
            ]);

            $redirect = add_query_arg(
                'action',
                $_GET['action'],
                get_admin_url() . 'admin.php?' . sprintf('?page=%s', $_REQUEST['page'])
            );

            wp_redirect($redirect);

            exit();
        }
    }

    /**
     * @return void
     */
    public function process_bundle_action()
    {
        if (isset($_POST['action']) && ($_POST['action'] === 'add-to-bundle' || $_POST['action'] === 'remove-from-bundle')) {
            $bundle_ids = explode(',', get_option('growtype_ai_bundle_ids'));

            $models_id = $_POST['model'];

            if ($_POST['action'] === 'add-to-bundle') {
                $bundle_ids = array_unique(array_merge($bundle_ids, $models_id));
            }

            if ($_POST['action'] === 'remove-from-bundle') {
                $bundle_ids = array_unique(array_diff($bundle_ids, $models_id));
            }

            update_option('growtype_ai_bundle_ids', implode(',', array_filter($bundle_ids)));

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
    public function process_download_cloudinary_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'index-download-model-images' || $_GET['action'] === 'index-download-all-models-images')) {
            if ($_GET['action'] === 'index-download-model-images') {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                    'queue' => 'download-cloudinary-folder',
                    'payload' => json_encode([
                        'model_id' => $_GET['item']
                    ]),
                    'attempts' => 0,
                    'reserved_at' => wp_date('Y-m-d H:i:s'),
                    'available_at' => date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + 5),
                    'reserved' => 0
                ]);
            } elseif ($_GET['action'] === 'index-download-all-models-images') {
                $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE, [
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

                    Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                        'queue' => 'download-cloudinary-folder',
                        'payload' => json_encode([
                            'model_id' => $model['id']
                        ]),
                        'attempts' => 0,
                        'reserved_at' => wp_date('Y-m-d H:i:s'),
                        'available_at' => date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + $seconds),
                        'reserved' => 0
                    ]);

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
        if (isset($_POST['action']) && ($_POST['action'] === 'download-zip')) {
            $models_ids = $_POST['model'];

            foreach ($models_ids as $model_id) {
                echo '<iframe src="https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai-models&action=download-ziped-model&model_id=' . $model_id . '"></iframe>';
            }

            echo '<script>setTimeout(function (){window.location.href = "https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai-models&paged='.$_POST['paged'].'";},3000)</script>';

            exit();
        }

        if (isset($_GET['action']) && ($_GET['action'] === 'download-ziped-model')) {
            $model_id = $_GET['model_id'];
            $this->download_ziped_model($model_id);
        }
    }

    public function download_ziped_model($model_id)
    {
        $model_details = growtype_ai_get_model_details($model_id);

        if (empty($model_details)) {
            $this->redirect_index();
        }

        $image_folder = $model_details['image_folder'];
        $image_folder_path = growtype_ai_get_upload_dir($image_folder);
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
        if (isset($_POST['update_item_id']) && !empty($_POST['update_item_id'])) {
            $model = $_POST['model'];
            $settings = $_POST['settings'];

            if (isset($settings['tags']) && !empty($settings['tags'])) {
                $settings['tags'] = json_encode(explode(',', $settings['tags']));
            }

            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODELS_TABLE, $model, $_POST['update_item_id']);

            Growtype_Ai_Database_Crud::update_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE,
                [
                    [
                        'key' => 'model_id',
                        'values' => [$_POST['update_item_id']]
                    ]
                ],
                [
                    'reference_key' => 'meta_key',
                    'update_value' => 'meta_value'
                ],
                $settings
            );
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

            wp_redirect(get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&action=%s&item=%s', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $_GET['item']));

            exit();
        }
    }

    /**
     * @return void
     */
    public function process_generate_image_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'generate-images' || $_GET['action'] === 'index-generate-images')) {
            $model_id = isset($_GET['model']) ? $_GET['model'] : null;

            if (!empty($model_id)) {
                if ($_GET['action'] === 'generate-images' || $_GET['action'] === 'index-generate-images') {
                    $crud = new Leonardo_Ai_Crud();
                    $generate_details = $crud->generate_model($model_id);

                    $redirect_args = [
                        'message_type' => 'custom',
                        'message' => sprintf(__('%d image is generating. User nr: %s. Image prompt: %s', 'growtype-ai'), 1, $generate_details['user_nr'], $generate_details['image_prompt']),
                    ];
                }
            }

            if ($_GET['action'] === 'generate-images') {
                $this->redirect_single($redirect_args ?? []);
            }

            if ($_GET['action'] === 'index-generate-images') {
                $this->redirect_index();
            }
        }
    }

    /**
     * @return void
     */
    public function process_generate_content_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'generate-model-content' || $_GET['action'] === 'generate-image-content' || $_GET['action'] === 'regenerate-image-content')) {
            $openai_crud = new Openai_Crud();

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
    }

    /**
     * @return void
     */
    public function process_retrieve_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'retrieve-images' || $_GET['action'] === 'retrieve-images-all')) {
            $crud = new Leonardo_Ai_Crud();

            $amount = 15;

            $model_id = isset($_GET['item']) ? $_GET['item'] : null;

            $users_credentials = Leonardo_Ai_Crud::user_credentials();

            foreach ($users_credentials as $user_nr => $user_credentials) {

                if (empty($user_credentials['user_id'])) {
                    continue;
                }

                $generations = $crud->retrieve_models($amount, $model_id, $user_nr);

                if (empty($generations)) {
                    continue;
                }
            }

            if ($_GET['action'] === 'retrieve-images') {
                $this->redirect_single([
                    'message_type' => 'custom',
                    'message' => sprintf(__('%d images are retrieving.', 'growtype-ai'), $amount),
                ]);
            } else {
                $this->redirect_index();
            }
        }
    }

    /**
     * @return void
     */
    public function process_optimize_images_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'upscale-images') {

            $model_id = isset($_GET['model']) ? $_GET['model'] : null;

            if (!empty($model_id)) {
                $model_images = growtype_ai_get_model_images($model_id);

                foreach ($model_images as $image) {
                    $image_id = $image['id'];

                    if (isset($image['settings']['real_esrgan'])) {
                        continue;
                    }

                    $optimization_type = 'local';

                    if ($optimization_type === 'external') {
                        $cloudinary_public_id = growtype_ai_get_cloudinary_public_id($image);

                        $cloudinary = new Cloudinary_Crud();
                        $asset = $cloudinary->get_asset($cloudinary_public_id);

                        if (isset($asset['context']['custom']['real_esrgan'])) {
                            if (!isset($image['settings']['real_esrgan'])) {
                                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $image_id,
                                    'meta_key' => 'real_esrgan',
                                    'meta_value' => 'true',
                                ]);
                            }

                            if (!isset($image['settings']['compressed'])) {
                                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $image_id,
                                    'meta_key' => 'compressed',
                                    'meta_value' => 'true',
                                ]);
                            }

                            continue;
                        }

                        $upscale_image_url = $asset['url'];

                        $real_esrgan = new Replicate();
                        $real_esrgan->upscale($upscale_image_url, [
                            'id' => $image['id'],
                            'public_id' => $cloudinary_public_id
                        ]);
                    } else {
                        growtype_ai_init_job('upscale-image', json_encode([
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
        $model = growtype_ai_get_model_details($id);

        $images_saving_location = growtype_ai_get_images_saving_location();

        if ($images_saving_location === 'cloudinary') {
            $crud = new Cloudinary_Crud();
            $crud->delete_folder_with_assets($model['image_folder']);
        }

        $images = growtype_ai_get_model_images($id);

        /**
         * Remove images
         */
        if (!empty($images)) {
            foreach ($images as $image) {
                $img_path = growtype_ai_get_image_path($image['id']);

                if (!empty($img_path)) {
                    unlink(growtype_ai_get_image_path($image['id']));
                }
            }
        }

        $model_images_dir = growtype_ai_get_upload_dir() . '/' . $model['image_folder'];

        rmdir($model_images_dir);

        return Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODELS_TABLE, [$id]);
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

    public function redirect_index()
    {
        $paged = isset($_GET['paged']) ? $_GET['paged'] : 1;

        $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?page=%s&paged=%s', $_REQUEST['page'], $paged);

        $redirect = add_query_arg([],
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
                Growtype_Ai_Crud::delete_image($image_id);
            }

            d('test');

            $this->redirect_single();
        }
    }

    /**
     * @return void
     */
    public function process_update_model_images_colors()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'update-model-images-colors') {

            $images = growtype_ai_get_model_images($_GET['model']);

            foreach ($images as $image) {
                growtype_ai_init_job('extract-image-colors', json_encode([
                    'image_id' => $image['id'],
                    'update_colors' => true
                ]), 1);
            }

            $this->redirect_single();
        }
    }

    public function prepare_inner_table($id)
    {
        $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE, [
            [
                'key' => 'id',
                'values' => [$id],
            ]
        ]);

        $model = isset($models[0]) ? $models[0] : null;

        if (empty($model)) {
            return;
        }

        $models_settings = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
            [
                'key' => 'model_id',
                'values' => [$model['id']],
            ]
        ]);

        ?>

        <h2>Model settings</h2>

        <table class="form-table">
            <tbody>
            <?php foreach ($model as $key => $item) {
                if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }
                ?>
                <tr>
                    <th scope="row">
                        <label for=""><?php echo $key ?></label>
                        <?php
                        if ($key === 'prompt') {
                            echo '<p class="text">Use <code>{prompt_variable}</code> to insert random prompt variable</p>';
                        }
                        ?>
                    </th>
                    <td>
                        <?php if (in_array($key, ['prompt', 'negative_prompt'])) { ?>
                            <textarea class="large-text code" rows="5" name="model[<?php echo $key ?>]"><?php echo $item ?></textarea>
                        <?php } else { ?>
                            <input class="regular-text code" type="text" name="model[<?php echo $key ?>]" value="<?php echo $item ?>"/>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <h2>Model parameters</h2>

        <table class="form-table">
            <tbody>
            <?php

            $required_keys = [
                'featured_in',
                'model_id',
                'title',
                'description',
                'tags',
                'prompt_variables',
                'init_generation_image_id',
                'categories',
                'high_contrast'
            ];

            $existing_keys = array_pluck($models_settings, 'meta_key');

            foreach ($required_keys as $required_key) {
                if (!in_array($required_key, $existing_keys)) {
                    array_push($models_settings, [
                        'meta_key' => $required_key,
                        'meta_value' => '',
                    ]);
                }
            }

            /**
             * Reorder settings to some values be on top
             */
            $models_settings_reordered = [];

            foreach ($models_settings as $item) {
                if (in_array($item['meta_key'], ['featured_in'])) {
                    array_unshift($models_settings_reordered, $item);
                } else {
                    array_push($models_settings_reordered, $item);
                }
            }

            foreach ($models_settings_reordered as $item) {
                if (in_array($item['meta_key'], ['id', 'created_at', 'updated_at'])) {
                    continue;
                }

                $meta_value = $item['meta_value'];

                if ($item['meta_key'] === 'tags' && !empty(json_decode($item['meta_value']))) {
                    $meta_value = implode(',', json_decode($item['meta_value']));
                    $meta_value = strtolower($meta_value);
                }

                if ($item['meta_key'] === 'prompt_variables' && empty($item['meta_value'])) {
                    $meta_value = 'white, black, and little bit gold | red, blue, white, gray | rose gold  blue | red, orange, yellow, green, blue, purple, pink | Red and Yellow|Pink and Purple|Blue and White|Orange and Yellow|Red and White|Pink and White|Purple and White|Yellow and White|Blue and Yellow|Pink and Red|Orange and Red|Red and Purple|Yellow and Orange|Pink and Yellow|Purple and Pink|Blue and Purple|Red and Orange|Yellow and Green|Purple and Blue|Pink and Orange|Green and White';
                }

                ?>
                <tr>
                    <th scope="row">
                        <label for=""><?php echo $item['meta_key'] ?></label>
                    </th>
                    <td>
                        <?php if (in_array($item['meta_key'], ['prompt', 'negative_prompt', 'description', 'tags', 'prompt_variables', 'image_prompts'])) { ?>
                            <textarea class="large-text code" rows="5" name="settings[<?php echo $item['meta_key'] ?>]"><?php echo $meta_value ?></textarea>
                        <?php } elseif ($item['meta_key'] === 'featured_in') {
                            $meta_value = !empty($meta_value) ? json_decode($meta_value, true) : [];
                            ?>
                            <select name="settings[<?php echo $item['meta_key'] ?>][]" multiple>
                                <option value="">nowhere</option>
                                <option value="artdecorio" <?= in_array('artdecorio', $meta_value) ? 'selected' : '' ?>>artdecorio.com</option>
                                <option value="sexy" <?= in_array('sexy', $meta_value) ? 'selected' : '' ?>>sexy</option>
                            </select>
                        <?php } elseif ($item['meta_key'] === 'categories') { ?>
                            <ul class="category-select">
                                <?php
                                $art_categories = growtype_ai_get_art_categories();
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

                            <input type="hidden" name="settings[categories]" value=""/>

                        <?php } else { ?>
                            <input class="regular-text code" type="text" name="settings[<?php echo $item['meta_key'] ?>]" value="<?php echo $meta_value ?>"/>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <input type="hidden" name="update_item_id" value="<?php echo $id ?>">

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>

        <br>
        <hr>
        <br>
        <br>

        <div style="display:flex;justify-content: flex-end;margin-bottom: 40px;">
            <div style="display: flex;flex-wrap: wrap;gap:10px;">
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Generate model details', 'growtype-ai') . '</a>', $_REQUEST['page'], 'generate-model-content', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Generate images details', 'growtype-ai') . '</a>', $_REQUEST['page'], 'generate-image-content', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Re-Generate images details', 'growtype-ai') . '</a>', $_REQUEST['page'], 'regenerate-image-content', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Upscale images', 'growtype-ai') . '</a>', $_REQUEST['page'], 'upscale-images', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Generate image', 'growtype-ai') . '</a>', $_REQUEST['page'], 'generate-images', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Retrieve image', 'growtype-ai') . '</a>', $_REQUEST['page'], 'retrieve-images', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Pull images', 'growtype-ai') . '</a>', $_REQUEST['page'], 'pull-model-images', $id) ?>
                <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Update colors', 'growtype-ai') . '</a>', $_REQUEST['page'], 'update-model-images-colors', $id) ?>
            </div>
        </div>

        <?php
        $model_images = growtype_ai_get_model_images($id);
        ?>

        <div class="b-imgs" style="gap: 10px;display: grid;">
            <?php foreach (array_reverse($model_images) as $image) { ?>
                <?= Growtype_Ai_Admin_Images::preview_image($image) ?>
            <?php } ?>
        </div>

        <?= Growtype_Ai_Admin_Images::image_delete_ajax() ?>

        <script>
            $ = jQuery;

            /**
             * Category select
             */

            window.categorySelect = '<?php echo !empty(growtype_ai_get_model_single_setting($_GET['model'], 'categories')) ? growtype_ai_get_model_single_setting($_GET['model'], 'categories')['meta_value'] : "" ?>';

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
}

