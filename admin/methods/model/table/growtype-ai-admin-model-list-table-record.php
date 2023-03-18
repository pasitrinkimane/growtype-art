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
        if ((isset($_REQUEST['page']) && $_REQUEST['page'] === Growtype_Ai_Admin::PAGE_NAME || isset($_REQUEST['post_type']) && $_REQUEST['post_type'] === Growtype_Ai_Admin::POST_TYPE) && isset($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
            $action2 = $_REQUEST['action2'] ?? '';

            if ('delete' === $action || 'delete' === $action2) {
                $nonce = esc_attr($_REQUEST['_wpnonce']);

                if (!wp_verify_nonce($nonce, Growtype_Ai_Admin::DELETE_NONCE)) {
                    die('Go get a life script kiddies');
                } else {
                    self::delete_item(absint($_GET['item']));

                    $this->update_url();
                }
            }

            if ($action === 'bulk_delete' || $action2 === 'bulk_delete') {
                $delete_ids = esc_sql($_POST['items']);

                foreach ($delete_ids as $id) {
                    self::delete_item($id);
                }

                $this->update_url();
            }
        }
    }

    /**
     * @return void
     */
    public function process_bundle_action()
    {
        if (isset($_POST['action']) && ($_POST['action'] === 'add-to-bundle' || $_POST['action'] === 'remove-from-bundle')) {
            $bundle_ids = explode(',', get_option('growtype_ai_bundle_ids'));
            $new_ids = $_POST['items'];

            if ($_POST['action'] === 'add-to-bundle') {
                $bundle_ids = array_unique(array_merge($bundle_ids, $new_ids));
            }

            if ($_POST['action'] === 'remove-from-bundle') {
                $bundle_ids = array_unique(array_diff($bundle_ids, $new_ids));
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
            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODELS_TABLE, [
                'prompt' => 'Sunset mountain',
                'negative_prompt' => 'Palm tree, beach, Sea',
                'reference_id' => growtype_ai_generate_reference_id(),
                'provider' => get_option('growtype_ai_images_saving_location'),
                'image_folder' => '',
                'image_location' => '',
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

            Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODELS_TABLE, $model, $_POST['update_item_id']);

            Growtype_Ai_Database::update_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                ['key' => 'model_id', 'values' => [$_POST['update_item_id']]]
            ], ['reference_key' => 'meta_key', 'update_value' => 'meta_value'], $settings);
        }
    }

    /**
     * @return void
     */
    public function process_sync_model_images_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'sync-model-images') {
//            growtype_ai_init_job('sync-model-images', json_encode([
//                'model_id' => $_GET['item']
//            ]), 10);

            $crud = new Cloudinary_Crud();
            $crud->sync_images($_GET['item']);

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

            if ($_GET['action'] === 'generate-images' || $_GET['action'] === 'index-generate-images') {
                $crud = new Leonardo_Ai_Crud();
                $crud->generate_model($_GET['item']);
            }

            $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&action=%s&item=%s', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $_GET['item']);
            $page = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;

            if ($_GET['action'] === 'index-generate-images') {
                $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&paged=%s', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], $page);
            }

            $redirect = add_query_arg(
                'message',
                $_GET['action'],
                $redirect_url
            );

            wp_redirect($redirect);

            exit();
        }
    }

    /**
     * @return void
     */
    public function process_generate_content_action()
    {
        if (isset($_GET['action']) && ($_GET['action'] === 'generate-model-content' || $_GET['action'] === 'generate-image-content')) {
            $openai_crud = new Openai_Crud();

            if ($_GET['action'] === 'generate-model-content') {
                $openai_crud->format_models(null, false, $_GET['item']);
            }

            if ($_GET['action'] === 'generate-image-content') {
                $openai_crud->format_model_images($_GET['item']);
            }

            $redirect = add_query_arg(
                'message',
                $_GET['action'],
                get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&action=%s&item=%s', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $_GET['item'])
            );

            wp_redirect($redirect);

            exit();
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
                $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&action=%s&item=%s', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $_GET['item']);
            } else {
                $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?page=%s', $_REQUEST['page']);
            }

            $redirect = add_query_arg(
                'message',
                $_GET['action'],
                $redirect_url
            );

            wp_redirect($redirect);

            exit();
        }
    }

    /**
     * @return void
     */
    public function process_optimize_images_action()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'optimize-images') {
            $model_images = growtype_ai_get_model_images($_GET['item']);

            foreach ($model_images as $image) {
                $image_id = $image['id'];

                if (isset($image['settings']['real_esrgan'])) {
                    continue;
                }

                $cloudinary_public_id = growtype_ai_get_cloudinary_public_id($image);

                $cloudinary = new Cloudinary_Crud();
                $asset = $cloudinary->get_asset_details($cloudinary_public_id);

                if (isset($asset['context']['custom']['real_esrgan'])) {
                    if (!isset($image['settings']['real_esrgan'])) {
                        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                            'image_id' => $image_id,
                            'meta_key' => 'real_esrgan',
                            'meta_value' => 'true',
                        ]);
                    }

                    if (!isset($image['settings']['compressed'])) {
                        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
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
            }

            $redirect_url = get_admin_url() . 'admin.php?' . sprintf('?post_type=%s&page=%s&action=%s&item=%s', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $_GET['item']);

            $redirect = add_query_arg(
                'message',
                $_GET['action'],
                $redirect_url
            );

            wp_redirect($redirect);

            exit();
        }
    }

    public function delete_item($id)
    {
        $models = growtype_ai_get_model_details($id);
        $crud = new Cloudinary_Crud();

        $crud->delete_folder($models['image_folder']);

        return Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODELS_TABLE, [$id]);
    }

    public function update_url()
    {
        $redirect = $this->redirect;

        add_action('admin_footer', function () use ($redirect) {
            ?>
            <script>
                history.pushState({}, null, '<?php echo $redirect ?>');
            </script>
            <?php
        });
    }

    public function prepare_inner_table($id)
    {
        $models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE, [
            [
                'key' => 'id',
                'values' => [$id],
            ]
        ]);

        $model = isset($models[0]) ? $models[0] : null;

        if (empty($model)) {
            return;
        }

        $models_settings = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
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
                        <?php if (in_array($item['meta_key'], ['prompt', 'negative_prompt', 'description'])) { ?>
                            <textarea class="large-text code" rows="5" name="settings[<?php echo $item['meta_key'] ?>]"><?php echo $meta_value ?></textarea>
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

        <?php
        $model_images = growtype_ai_get_model_images($id);
        ?>

        <div style="display:flex;justify-content: flex-end;">
            <?php echo sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s" class="button button-primary" style="margin-right: 15px;">' . __('Generate model details', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'generate-model-content', $id) ?>
            <?php echo sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s" class="button button-primary" style="margin-right: 15px;">' . __('Generate image details', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'generate-image-content', $id) ?>
            <?php echo '<span style="margin-left: 0px;margin-right: 10px;line-height: 25px;">-</span>' ?>
            <?php echo sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s" class="button button-primary" style="margin-right: 15px;">' . __('Optimize images', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'optimize-images', $id) ?>
            <?php echo sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s" class="button button-primary" style="margin-right: 15px;">' . __('Generate image', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'generate-images', $id) ?>
            <?php echo sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s" class="button button-primary" style="margin-right: 15px;">' . __('Retrieve image', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'retrieve-images', $id) ?>
            <?php echo sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s" class="button button-primary">' . __('Sync images', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'sync-model-images', $id) ?>
        </div>

        <div style="display: flex;flex-wrap: wrap;margin-top: 50px;">
            <?php foreach (array_reverse($model_images) as $image) {
                $caption = isset($image['settings']['caption']) ? $image['settings']['caption'] : null;
                $alt_text = isset($image['settings']['alt_text']) ? $image['settings']['alt_text'] : null;
                $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : null;
                $tags = !empty($tags) ? implode(', ', $tags) : null;

                ?>
                <div style="max-width: 200px;">
                    <img src="https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/<?php echo $image['folder'] ?>/<?php echo $image['name'] ?>.<?php echo $image['extension'] ?>" alt="" style="max-width: 100%;">
                    <div style="padding: 5px;">
                        <p><b>Caption</b>: <?php echo $caption ?></p>
                        <p><b>Alt text</b>: <?php echo $alt_text ?></p>
                        <p><b>Tags</b>: <?php echo $tags ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php
    }
}

