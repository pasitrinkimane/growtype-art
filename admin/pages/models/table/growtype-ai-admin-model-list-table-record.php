<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

class Growtype_Ai_Admin_Result_List_Table_Record
{
    public function __construct()
    {
        $this->redirect = admin_url('edit.php?post_type=' . Growtype_Ai_Admin::POST_TYPE . '&page=' . Growtype_Ai_Admin::PAGE_NAME);
    }

    /**
     * @return void
     */
    public function process_delete_action()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Ai_Admin::PAGE_NAME && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'delete' || $_REQUEST['action'] === 'bulk_delete')) {
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
                'message',
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
                'message',
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
    public function process_update_action()
    {
        if (isset($_POST['update_item_id']) && !empty($_POST['update_item_id'])) {
            $model = $_POST['model'];
            $settings = $_POST['settings'];

            if (isset($settings['tags']) && !empty($settings['tags'])) {
                $settings['tags'] = json_encode(explode(',', $settings['tags']));
            }

            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODELS_TABLE, $model, $_POST['update_item_id']);

            Growtype_Ai_Database_Crud::update_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                ['key' => 'model_id', 'values' => [$_POST['update_item_id']]]
            ], ['reference_key' => 'meta_key', 'update_value' => 'meta_value'], $settings);
        }
    }

    /**
     * @return void
     */
    public function process_sync_model_images_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'pull-model-images') {
//            growtype_ai_init_job('sync-model-images', json_encode([
//                'model_id' => $_GET['item']
//            ]), 10);

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
                    $crud->generate_model($model_id);
                }
            }

            if ($_GET['action'] === 'generate-images') {
                $this->redirect_single();
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
        if (isset($_GET['action']) && ($_GET['action'] === 'generate-model-content' || $_GET['action'] === 'generate-image-content')) {
            $openai_crud = new Openai_Crud();

            $model_id = isset($_GET['model']) ? $_GET['model'] : null;

            if (!empty($model_id)) {
                if ($_GET['action'] === 'generate-model-content') {
                    $openai_crud->format_models(null, false, $_GET['model']);
                }

                if ($_GET['action'] === 'generate-image-content') {
                    $openai_crud->format_model_images($_GET['model']);
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

            $amount = 1;

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
                $this->redirect_single();
            } else {
                $this->redirect_index();
            }
        }
    }

    /**
     * @return void
     */
    public function process_download_action()
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
                'message',
                $_GET['action'],
                get_admin_url() . 'admin.php?' . sprintf('page=%s&paged=%s', $_REQUEST['page'], $_REQUEST['paged'])
            );

            wp_redirect($redirect);

            exit();
        }
    }

    public function process_delete_image()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'delete-image') {

            $image_id = isset($_GET['image']) ? $_GET['image'] : null;

            if (!empty($image_id)) {
                Growtype_Ai_Crud::delete_image($image_id);
            }

            $this->redirect_single();
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
                unlink(growtype_ai_get_image_path($image['id']));
            }
        }

        $model_images_dir = growtype_ai_get_upload_dir() . '/' . $model['image_folder'];

        rmdir($model_images_dir);

        return Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODELS_TABLE, [$id]);
    }

    public function redirect_single()
    {
        $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?page=%s&action=%s&model=%s', $_REQUEST['page'], 'edit', $_GET['model']);

        $redirect = add_query_arg(
            'message',
            $_GET['action'],
            $redirect_url
        );

        wp_redirect($redirect);

        exit();
    }

    public function redirect_index()
    {
        $paged = isset($_GET['paged']) ? $_GET['paged'] : 1;

        $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?page=%s&paged=%s', $_REQUEST['page'], $paged);

        $redirect = add_query_arg(
            'message',
            $_GET['action'],
            $redirect_url
        );

        wp_redirect($redirect);

        exit();
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
                'model_id',
                'title',
                'description',
                'tags',
                'prompt_variables',
                'categories'
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

            foreach ($models_settings as $item) {
                if (in_array($item['meta_key'], ['id', 'created_at', 'updated_at'])) {
                    continue;
                }

                $meta_value = $item['meta_value'];

                if ($item['meta_key'] === 'tags' && !empty(json_decode($item['meta_value']))) {
                    $meta_value = implode(',', json_decode($item['meta_value']));
                    $meta_value = strtolower($meta_value);
                }

                ?>
                <tr>
                    <th scope="row">
                        <label for=""><?php echo $item['meta_key'] ?></label>
                    </th>
                    <td>
                        <?php if (in_array($item['meta_key'], ['prompt', 'negative_prompt', 'description', 'prompt_variables'])) { ?>
                            <textarea class="large-text code" rows="5" name="settings[<?php echo $item['meta_key'] ?>]"><?php echo $meta_value ?></textarea>
                        <?php } elseif ($item['meta_key'] === 'categories') { ?>
                            <ul class="category-select">
                                <?php foreach (growtype_ai_get_images_categories() as $category => $values) { ?>
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

        <div style="display:flex;justify-content: flex-end;">
            <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary" style="margin-right: 15px;">' . __('Generate model details', 'growtype-ai') . '</a>', $_REQUEST['page'], 'generate-model-content', $id) ?>
            <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary" style="margin-right: 15px;">' . __('Generate images details', 'growtype-ai') . '</a>', $_REQUEST['page'], 'generate-image-content', $id) ?>
            <?php echo '<span style="margin-left: 0px;margin-right: 10px;line-height: 25px;">-</span>' ?>
            <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary" style="margin-right: 15px;">' . __('Upscale images', 'growtype-ai') . '</a>', $_REQUEST['page'], 'upscale-images', $id) ?>
            <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary" style="margin-right: 15px;">' . __('Generate image', 'growtype-ai') . '</a>', $_REQUEST['page'], 'generate-images', $id) ?>
            <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary" style="margin-right: 15px;">' . __('Retrieve image', 'growtype-ai') . '</a>', $_REQUEST['page'], 'retrieve-images', $id) ?>
            <?php echo sprintf('<a href="?page=%s&action=%s&model=%s" class="button button-primary">' . __('Pull images', 'growtype-ai') . '</a>', $_REQUEST['page'], 'pull-model-images', $id) ?>
        </div>

        <?php
        $model_images = growtype_ai_get_model_images($id);
        ?>

        <div style="display: flex;flex-wrap: wrap;margin-top: 50px;">
            <?php foreach (array_reverse($model_images) as $image) {
                $prompt = isset($image['settings']['prompt']) ? $image['settings']['prompt'] : '';
                $caption = isset($image['settings']['caption']) ? $image['settings']['caption'] : '';
                $alt_text = isset($image['settings']['alt_text']) ? $image['settings']['alt_text'] : '';
                $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : '';
                $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : '';
                $tags = !empty($tags) ? implode(', ', $tags) : '';

                $compressed = isset($image['settings']['compressed']) ? true : false;
                $real_esrgan = isset($image['settings']['real_esrgan']) ? true : false;

                $img_url = growtype_ai_get_image_url($image['id']);

                $main_colors = isset($image['settings']['main_colors']) ? json_decode($image['settings']['main_colors'], true) : [];

                if (!empty($img_url)) { ?>
                    <div class="image" style="max-width: 20%;">
                        <a href="<?php echo $img_url ?>?v=<?php echo time() ?>" target="_blank">
                            <img src="<?php echo $img_url ?>?v=<?php echo time() ?>" alt="" style="max-width: 100%;">
                        </a>
                        <div>
                            <?php echo sprintf('<a href="?page=%s&action=%s&image=%s&model=%s" class="button button-primary button-delete" style="margin-right: 15px;" data-id="' . $image['id'] . '">' . __('Delete', 'growtype-ai') . '</a>', $_REQUEST['page'], 'delete-image', $image['id'], $_GET['model']) ?>
                        </div>
                        <?php
                        if ($real_esrgan) {
                            echo '<span style="color: green;display:inline-block;padding-top: 20px;">Upscaled</span>';
                        }
                        ?>

                        <?php
                        if ($compressed) {
                            echo '<span style="color: green;display:inline-block;padding-top: 20px;">Compressed</span>';
                        }
                        ?>
                        <div style="padding: 5px;">
                            <p><b>Main Colors</b>: <?php echo implode(',', $main_colors) ?></p>
                            <p><b>Prompt</b>: <?php echo $prompt ?></p>
                            <p><b>Caption</b>: <?php echo $caption ?></p>
                            <p><b>Alt text</b>: <?php echo $alt_text ?></p>
                            <p><b>Tags</b>: <?php echo $tags ?></p>
                        </div>
                    </div>
                <?php } ?>

            <?php } ?>
        </div>

        <script>
            $ = jQuery;

            $('.button-delete').click(function (e) {
                e.preventDefault();

                let image = $(this).closest('.image');

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'remove_image',
                        image_id: $(this).attr('data-id'),
                    },
                    success: function (res) {
                        image.remove();
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            })

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

