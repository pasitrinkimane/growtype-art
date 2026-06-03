<?php

/**
 * Owns menu registration, screen options, the main page callback,
 * admin messages, and action processing for the Models admin page.
 */
class Growtype_Art_Admin_Models_Page
{
    public $items_obj;
    public $item_obj;

    public function __construct()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        add_action('admin_menu', [$this, 'items_tab_init']);
    }

    // ──────────────────────────────────────────────
    // Menu / screen
    // ──────────────────────────────────────────────

    public static function set_screen($status, $option, $value)
    {
        return $value;
    }

    public function items_tab_init(): void
    {
        $hook = add_submenu_page(
            'growtype-art',
            __('Models', 'growtype-art'),
            __('Models', 'growtype-art'),
            'manage_options',
            'growtype-art-models',
            [$this, 'growtype_art_result_callback'],
            1
        );

        add_action("load-$hook", [$this, 'screen_option']);
        add_action("load-$hook", [$this, 'process_actions']);
    }

    public function screen_option(): void
    {
        add_screen_option('per_page', [
            'label'   => 'Items',
            'default' => 10,
            'option'  => 'items_per_page',
        ]);

        $this->items_obj = new Growtype_Art_Admin_Result_List_Table();
    }

    // ──────────────────────────────────────────────
    // Main page callback
    // ──────────────────────────────────────────────

    public function growtype_art_result_callback(): void
    {
        $message = $this->show_message();

        $id              = isset($_GET['model']) && !is_array($_GET['model']) ? $_GET['model'] : '';
        $action          = $_GET['action']          ?? '';
        $offset          = (int)($_GET['offset']    ?? 0);
        $limit           = (int)($_GET['limit']     ?? 50);
        $filter          = $_GET['filter']          ?? '';
        $file_type       = $_GET['file_type']       ?? '';
        $hide_private    = (int)($_GET['hide_private']  ?? 0);
        $has_relations   = (int)($_GET['has_relations'] ?? 0);
        $parent_image_id = $_GET['parent_image_id'] ?? '';

        $query_params = [
            'filter'          => $filter,
            'file_type'       => $file_type,
            'hide_private'    => $hide_private,
            'has_relations'   => $has_relations,
            'parent_image_id' => $parent_image_id,
        ];

        $total_images = $action === 'edit'
            ? growtype_art_get_model_total_images_amount($id, $query_params)
            : 0;

        $title = $action === 'edit'
            ? __('Edit records id', 'growtype-art') . ': ' . $id
            : __('Models', 'growtype-art') . ' ' . sprintf(
                '<a href="?page=%s&action=%s" class="page-title-action">%s</a>',
                $_REQUEST['page'], 'create-model', __('Add new', 'growtype-art')
            );
        ?>
        <style>.fixed .column-slug { width: initial; }</style>

        <div class="wrap">
            <header class="page-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #c3c4c7;">
                <h2 style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0;">
                    <?php echo $title ?>
                    <?php if ($action !== 'edit'): ?>
                        <div class="actions-box" style="display: flex; gap: 10px; margin-left: 10px;">
                            <?php echo sprintf('<a href="?page=%s&action=%s" class="button button-primary" style="display:none;">%s</a>', $_REQUEST['page'], 'retrieve-models', __('Retrieve images', 'growtype-art')) ?>
                            <?php echo sprintf('<a href="?page=%s&action=%s" class="button button-primary">%s</a>', $_REQUEST['page'], 'generate-models', __('Generate models', 'growtype-art')) ?>
                        </div>
                    <?php endif; ?>
                </h2>

                <?php if ($action === 'edit' && !empty($id)): ?>
                    <div class="page-actions" style="display: flex; gap: 10px; align-items: center;">
                        <?php
                        $model_details = growtype_art_get_model_details($id);
                        $model_slug = $model_details['settings']['slug'] ?? '';
                        $featured_in = $model_details['settings']['featured_in'] ?? [];

                        $featured_in_values = is_array($featured_in) ? $featured_in : (json_decode((string)$featured_in, true) ?: []);

                        if (!empty($model_slug)) {
                            foreach ($featured_in_values as $featured_in_value) {
                                $profile_url = sprintf('https://%s.com/profile/%s', $featured_in_value, $model_slug);
                                echo '<a href="' . esc_url($profile_url) . '" target="_blank" class="button button-secondary">' . esc_html($featured_in_value) . '</a>';
                            }
                        }

                        $api_url = !empty($featured_in_values) 
                            ? rest_url('growtype-art/v1/retrieve/character/' . $featured_in_values[0] . '/' . $id)
                            : rest_url('growtype-art/v1/retrieve/model/' . $id);
                        $api_url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $api_url);
                        ?>
                        <a href="<?php echo esc_url($api_url); ?>" target="_blank" class="button button-secondary">Api</a>
                    </div>
                <?php endif; ?>
            </header>

            <?php echo $message ?>

            <?php if ($action === 'edit'): ?>
                <form method="post" enctype="multipart/form-data">
                    <?php $this->item_obj->prepare_inner_table($id); ?>
                </form>
            <?php else:
                $this->items_obj->prepare_items(); ?>
                <form id="models-filter" method="get">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                    <?php
                    $this->items_obj->search_box('Search', 'search');
                    $this->items_obj->display();
                    ?>
                </form>
            <?php endif; ?>

            <?php if ($action === 'edit'):
                $grouped_images      = growtype_art_get_model_images_grouped($id, 1000, 0, $query_params);
                $grouped_by_basename = Growtype_Art_Admin_Models_Images_View::build_grouped_by_basename($grouped_images);
                ?>

                <?php Growtype_Art_Admin_Models_Filters::render($query_params); ?>

                <?php
                Growtype_Art_Admin_Models_Images_View::render_groups(
                    $grouped_by_basename,
                    (int)$id,
                    $offset,
                    $limit,
                    (bool)$has_relations
                );
                ?>

                <?= Growtype_Art_Admin_Images::image_delete_ajax() ?>
                <?php echo Growtype_Art_Admin_Pages::render_pagination('growtype-art-models', $total_images, $offset, $limit); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────
    // Admin messages
    // ──────────────────────────────────────────────

    public function show_message(): string
    {
        if ('delete' === $this->items_obj->current_action() && isset($_REQUEST['item'])) {
            return '<div class="updated below-h2" id="message"><p>'
                . sprintf(__('ID %d was deleted.', 'growtype-art'), $_REQUEST['item'])
                . '</p></div>';
        }

        if ('bulk_delete' === $this->items_obj->current_action()) {
            return '<div class="updated below-h2" id="message"><p>'
                . sprintf(__('%d items were deleted.', 'growtype-art'), count($_POST['items']))
                . '</p></div>';
        }

        if (filter_input(INPUT_GET, 'message_type') === 'custom') {
            $message_content = filter_input(INPUT_GET, 'message');
            $status          = filter_input(INPUT_GET, 'status') ?: 'updated';
            if (!empty($message_content)) {
                return '<div class="' . $status . ' below-h2" id="message"><p>' . $message_content . '</p></div>';
            }
        }

        return '';
    }

    // ──────────────────────────────────────────────
    // Actions processing
    // ──────────────────────────────────────────────

    public function process_actions(): void
    {
        $this->item_obj = new Growtype_Art_Admin_Model_List_Table_Record();

        // Table-level actions
        $this->item_obj->process_delete_action();
        $this->item_obj->process_add_new_action();
        $this->item_obj->process_bundle_action();
        $this->item_obj->process_download_cloudinary_action();
        $this->item_obj->process_download_zip_action();
        $this->item_obj->process_retrieve_action();

        // Single-record actions
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
