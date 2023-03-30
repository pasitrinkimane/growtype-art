<?php

class Growtype_Ai_Admin_Models
{
    public $items_obj;
    public $item_obj;

    public function __construct()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);

        add_action('admin_menu', array ($this, 'items_tab_init'));
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
            'growtype-ai',
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
        $message = $this->get_message();

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
        </div>

        <?php
    }

    function get_message()
    {
        $message = '';

        if ('delete' === $this->items_obj->current_action()) {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('ID %d was deleted.', 'growtype-ai'), $_REQUEST['item']) . '</p></div>';
        } elseif ('bulk_delete' === $this->items_obj->current_action()) {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('%d items were deleted.', 'growtype-ai'), count($_POST['items'])) . '</p></div>';
        } elseif (filter_input(INPUT_GET, 'message') === 'generate-images') {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('%d image is generating.', 'growtype-ai'), 1) . '</p></div>';
        } elseif (filter_input(INPUT_GET, 'message') === 'retrieve-images') {
            $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('%d images are retrieving.', 'growtype-ai'), 5) . '</p></div>';
        }

        return $message;
    }

    /**
     * Init record related actions
     */
    function process_actions()
    {
        require_once GROWTYPE_AI_PATH . 'admin/pages/models/table/growtype-ai-admin-model-list-table-record.php';

        $this->item_obj = new Growtype_Ai_Admin_Result_List_Table_Record();

        /**
         * General table
         */

//        d($_GET);

        $this->item_obj->process_delete_action();
        $this->item_obj->process_add_new_action();
        $this->item_obj->process_bundle_action();

        $this->item_obj->process_download_action();

        $this->item_obj->process_retrieve_action();

        /**
         * Single record
         */
        $this->item_obj->process_update_action();
        $this->item_obj->process_sync_model_images_action();

        $this->item_obj->process_generate_image_action();

        $this->item_obj->process_generate_content_action();
        $this->item_obj->process_optimize_images_action();
        $this->item_obj->process_delete_image();
    }
}


