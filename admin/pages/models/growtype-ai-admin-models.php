<?php

class Growtype_Ai_Admin_Models
{
    public $items_obj;
    public $item_obj;

    public function __construct()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);

        add_action('admin_menu', array ($this, 'items_tab_init'));

        add_action('wp_ajax_growtype_ai_admin_update_model', array ($this, 'update_model_callback'));
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

        $settings_exists = isset($property_to_update[0]) && $property_to_update[0] === 'settings' ? true : false;

        if ($settings_exists) {
            $property_to_update = str_replace('"', '', stripslashes($property_to_update[1]));

            Growtype_Ai_Database_Crud::update_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE,
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
                    $property_to_update => sanitize_text_field($value)
                ]
            );
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
            'growtype-ai',
            __('Models', 'growtype-ai'),
            __('Models', 'growtype-ai'),
            'manage_options',
            'growtype-ai-models',
            array ($this, 'growtype_ai_result_callback'),
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

        require_once GROWTYPE_AI_PATH . 'admin/pages/models/table/growtype-ai-admin-model-list-table.php';
        $this->items_obj = new Growtype_Ai_Admin_Result_List_Table();
    }

    /**
     * Display callback for the submenu page.
     */
    function growtype_ai_result_callback()
    {
        $message = $this->show_message();

        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $id = isset($_GET['model']) ? $_GET['model'] : '';

        $title = $action === 'edit' ? (__('Edit records id', 'growtype-ai') . ': ' . $id) : __('Models', 'growtype-ai') . ' ' . sprintf('<a href="?page=%s&action=%s" class="page-title-action">' . __('Add new', 'growtype-ai') . '</a>', $_REQUEST['page'], 'create-model');

        ?>

        <div class="wrap">
            <h2><?php echo $title ?></h2>

            <?php echo $message ?>

            <form method="post">
                <?php
                if ($action === 'edit') {
                    $this->item_obj->prepare_inner_table($id);
                } else {
                    $this->items_obj->prepare_items();
                    $this->items_obj->search_box('Search', 'search');
                    $this->items_obj->display();
                }
                ?>
            </form>

            <?php if ($action === 'edit') { ?>
                <div class="b-imgs" style="gap: 10px;display: grid;">
                    <?php
                    $model_images = growtype_ai_get_model_images($id);
                    ?>
                    <?php foreach (array_reverse($model_images) as $image) { ?>
                        <?= Growtype_Ai_Admin_Images::preview_image($image['id']) ?>
                    <?php } ?>
                </div>

                <?= Growtype_Ai_Admin_Images::image_delete_ajax() ?>
            <?php } ?>
        </div>

        <?php
    }

    function show_message()
    {
        if ('delete' === $this->items_obj->current_action() && isset($_REQUEST['item'])) {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('ID %d was deleted.', 'growtype-ai'), $_REQUEST['item']) . '</p></div>';
        } elseif ('bulk_delete' === $this->items_obj->current_action()) {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('%d items were deleted.', 'growtype-ai'), count($_POST['items'])) . '</p></div>';
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
        require_once GROWTYPE_AI_PATH . 'admin/pages/models/table/growtype-ai-admin-model-list-table-record.php';

        $this->item_obj = new Growtype_Ai_Admin_Model_List_Table_Record();

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
        $this->item_obj->process_optimize_images_action();
        $this->item_obj->process_delete_image();
        $this->item_obj->process_update_model_images_colors();
    }
}


