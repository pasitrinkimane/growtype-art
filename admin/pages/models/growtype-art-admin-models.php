<?php

class Growtype_Art_Admin_Models
{
    public $items_obj;
    public $item_obj;

    public function __construct()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);

        add_action('admin_menu', array ($this, 'items_tab_init'));

        add_action('wp_ajax_growtype_art_admin_update_model', array ($this, 'update_model_callback'));
    }

    function update_model_callback()
    {
        $model_id = $_POST['model_id'];
        $value = $_POST['value'];
        $name = $_POST['name'];

        $property_to_update = explode('[', rtrim($name, ']'));

        if (is_array($value)) {
            $value = json_encode($value);
        }

        $update_type = $property_to_update[0] ?? '';
        $update_key = isset($property_to_update[1]) ? str_replace('"', '', stripslashes($property_to_update[1])) : '';

        if ($update_type === 'settings' && in_array($update_key, ['featured_in', 'tags'])) {
            $sanitized_value = sanitize_text_field($value);

            if ($update_key === 'tags') {
                $sanitized_value = preg_replace('/\s*,\s*/', ',', trim($sanitized_value, ','));

                if (!empty($sanitized_value)) {
                    $sanitized_value = explode(',', $sanitized_value);
                    $sanitized_value = !empty($sanitized_value) ? json_encode($sanitized_value) : '';
                }
            }

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
                [
                    $update_key => $sanitized_value
                ]
            );
        } elseif ($update_type === 'model' && in_array($update_key, ['provider'])) {
            Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                'provider' => sanitize_text_field($value),
            ], $model_id);
        }

        return wp_send_json(
            [
                'message' => __('Updated', 'growtype')
            ], 200);
    }

    public static function set_screen($status, $option, $value)
    {
        return $value;
    }

    /**
     * Create the All Users / Profile > Edit Profile and All Users Signups submenus.
     *
     * @since 2.0.0
     *
     */
    public function items_tab_init()
    {
        $hook = add_submenu_page(
            'growtype-art',
            __('Models', 'growtype-art'),
            __('Models', 'growtype-art'),
            'manage_options',
            'growtype-art-models',
            array ($this, 'growtype_art_result_callback'),
            1
        );

        add_action("load-$hook", [$this, 'screen_option']);
        add_action("load-$hook", [$this, 'process_actions']);
    }

    /**
     * Screen options
     */
    public function screen_option()
    {
        $option = 'per_page';

        $args = [
            'label' => 'Items',
            'default' => 10,
            'option' => 'items_per_page'
        ];

        add_screen_option($option, $args);

        require_once GROWTYPE_ART_PATH . 'admin/pages/models/table/growtype-art-admin-model-list-table.php';
        $this->items_obj = new Growtype_Art_Admin_Result_List_Table();
    }

    /**
     * Display callback for the submenu page.
     */
    function growtype_art_result_callback()
    {
        $message = $this->show_message();

        $id = isset($_GET['model']) ? $_GET['model'] : '';
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

        $total_images = growtype_art_get_model_total_images_amount($id);

        $title = $action === 'edit' ? (__('Edit records id', 'growtype-art') . ': ' . $id) : __('Models', 'growtype-art') . ' ' . sprintf('<a href="?page=%s&action=%s" class="page-title-action">' . __('Add new', 'growtype-art') . '</a>', $_REQUEST['page'], 'create-model');
        ?>

        <div class="wrap">
            <h2><?php echo $title ?></h2>

            <?php echo $message ?>

            <?php if ($action === 'edit') { ?>
                <form method="post" enctype="multipart/form-data">
                    <?php $this->item_obj->prepare_inner_table($id); ?>
                </form>
            <?php } else {
                $this->items_obj->prepare_items();
                ?>
                <form id="models-filter" method="get">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                    <?php
                    $this->items_obj->search_box('Search', 'search');
                    $this->items_obj->display();
                    ?>
                </form>
            <?php } ?>

            <?php if ($action === 'edit') {
                $grouped_images = growtype_art_get_model_images_grouped($id, 1000, 0);

                $grouped_by_basename = [];

                foreach ($grouped_images as $group_key => $grouped_image) {
                    foreach ($grouped_image as $group_image) {
                        $group_id = $group_image['id'];
                        if (!empty($group_image['settings']['parent_image_id'])) {
                            $group_id = $group_image['settings']['parent_image_id'];
                        }

                        if (!isset($grouped_by_basename[$group_key][$group_id])) {
                            $grouped_by_basename[$group_key][$group_id] = [];
                        }

                        $grouped_by_basename[$group_key][$group_id][] = $group_image;
                    }
                }

                foreach ($grouped_by_basename as $group_key => $images_group) {
                    if (empty($images_group)) {
                        continue;
                    }
                    ?>
                    <div>
                        <h3><?= ucfirst($group_key) ?> images</h3>
                        <div style="margin-bottom: 20px;">
                            <p style="margin: 0;"><?php echo sprintf('Total images: <b>%s</b>', growtype_art_get_model_images_group_stats($id, $group_key)['total']) ?></p>
                            <p style="margin: 0;"><?php echo sprintf('Nsfw images: <b>%s</b>', growtype_art_get_model_images_group_stats($id, $group_key)['nsfw']) ?></p>
                            <p style="margin: 0;"><?php echo sprintf('Naked images: <b>%s</b>', growtype_art_get_model_images_group_stats($id, $group_key)['naked']) ?></p>
                            <p style="margin: 0;"><?php echo sprintf('Featured images: <b>%s</b>', growtype_art_get_model_images_group_stats($id, $group_key)['featured']) ?></p>
                            <p style="margin: 0;"><?php echo sprintf('Cover images: <b>%s</b>', growtype_art_get_model_images_group_stats($id, $group_key)['cover']) ?></p>
                        </div>
                        <div class="b-imgs" style="gap: 10px;display: grid;">
                            <?php

                            $images_group = array_slice($images_group, $offset, $limit);

                            foreach ($images_group as $same_images_group) {
                                foreach ($same_images_group as $image) {
                                    echo Growtype_Art_Admin_Images::preview_image_from_data($image);
                                }
                            } ?>
                        </div>
                    </div>
                <?php } ?>

                <?= Growtype_Art_Admin_Images::image_delete_ajax() ?>
            <?php } ?>
        </div>

        <?php
        echo Growtype_Art_Admin_Pages::render_pagination('growtype-art-models', $total_images, $offset, $limit);
    }

    function show_message()
    {
        if ('delete' === $this->items_obj->current_action() && isset($_REQUEST['item'])) {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('ID %d was deleted.', 'growtype-art'), $_REQUEST['item']) . '</p></div>';
        } elseif ('bulk_delete' === $this->items_obj->current_action()) {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('%d items were deleted.', 'growtype-art'), count($_POST['items'])) . '</p></div>';
        } elseif (filter_input(INPUT_GET, 'message_type') === 'custom') {
            $message_content = filter_input(INPUT_GET, 'message');
            $status = filter_input(INPUT_GET, 'status');
            $status = empty($status) ? 'updated' : $status;
            $message = !empty($message_content) ? '<div class="' . $status . ' below-h2" id="message"><p>' . $message_content . '</p></div>' : '';
        }

        return isset($message) ? $message : '';
    }

    /**
     * Init record related actions
     */
    function process_actions()
    {
        require_once GROWTYPE_ART_PATH . 'admin/pages/models/table/growtype-art-admin-model-list-table-record.php';

        $this->item_obj = new Growtype_Art_Admin_Model_List_Table_Record();

        /**
         * General table
         */
        $this->item_obj->process_delete_action();
        $this->item_obj->process_add_new_action();
        $this->item_obj->process_bundle_action();
        $this->item_obj->process_download_cloudinary_action();
        $this->item_obj->process_download_zip_action();
        $this->item_obj->process_retrieve_action();

        /**
         * Single record
         */
        $this->item_obj->process_update_action();
        $this->item_obj->process_duplicate_model_action();
        $this->item_obj->process_sync_model_images_action();
        $this->item_obj->process_generate_image_action();
        $this->item_obj->process_generate_content_action();
        $this->item_obj->process_modify_images_action();
        $this->item_obj->process_delete_image();
        $this->item_obj->process_update_model_images_colors();
    }
}


