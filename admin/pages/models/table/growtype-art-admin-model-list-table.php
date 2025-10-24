<?php
/**
 * Models List Table class.
 */

defined('ABSPATH') || exit;

/**
 * List table class
 *
 * @since 2.0.0
 */
class Growtype_Art_Admin_Result_List_Table extends WP_List_Table
{
    public $items_count = 0;

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        parent::__construct(array (
            'ajax' => false,
            'plural' => 'models',
            'singular' => 'model',
            'screen' => get_current_screen()->id
        ));

        $action = $_GET['action'] ?? '';
        if (isset($_GET['page']) && $_GET['page'] === 'growtype-art-models' && $action !== 'edit') {
            add_action('admin_footer', array ($this, 'admin_enqueue_custom_scripts'), 100);
        }
    }

    function admin_enqueue_custom_scripts($hook)
    {
        ?>
        <script>
            jQuery('select[name="settings[featured_in][]"]').on('change', function (option) {
                let $this = jQuery(this)
                jQuery.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_update_model',
                        model_id: $this.closest('tr').attr('data-id'),
                        name: 'settings[featured_in]',
                        value: $this.val(),
                    },
                    success: function (res) {
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            });

            jQuery('select[name="model[provider][]"]').on('change', function (option) {
                let $this = jQuery(this)
                jQuery.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_update_model',
                        model_id: $this.closest('tr').attr('data-id'),
                        name: 'model[provider]',
                        value: $this.val(),
                    },
                    success: function (res) {
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            });

            jQuery('textarea[name="settings[tags]"]').on('focusout', function (option) {
                let $this = jQuery(this)
                jQuery.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_update_model',
                        model_id: $this.closest('tr').attr('data-id'),
                        name: 'settings[tags]',
                        value: $this.val(),
                    },
                    success: function (res) {
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            });
        </script>
        <?php
    }

    function extra_tablenav($which)
    {
        if ($which == "top") { ?>
            <div class="alignleft actions" style="display:flex;gap: 10px;">

                <div style="display: flex;align-items: center;justify-content: center;">
                    <input type="checkbox" name="model_is_in_bundle" <?php echo checked(isset($_REQUEST['model_is_in_bundle']) && $_REQUEST['model_is_in_bundle']) ?>>
                    <label for="">In bundle</label>
                </div>

                <?php
                $options = [
                    [
                        'value' => 'admin',
                        'title' => 'Created by admin',
                    ],
                    [
                        'value' => 'external_user',
                        'title' => 'Created by external user',
                    ]
                ];

                if ($options) { ?>
                    <select name="filter_models_action" class="ewc-filter-cat">
                        <option value="">All</option>
                        <?php foreach ($options as $option) { ?>
                            <option value="<?php echo $option['value']; ?>" <?php selected(isset($_REQUEST['filter_models_action']) && $option['value'] === $_REQUEST['filter_models_action']) ?>><?php echo $option['title']; ?></option>
                        <?php } ?>
                    </select>
                    <?php
                }
                ?>
            </div>

            <?php
            submit_button(__('Filter'), '', 'filter_action', false, array ('id' => 'post-query-submit'));
            ?>

            <div style="display: inline-block;margin-left: 5px;">
                <div class="actions-box" style="display: flex;gap: 10px;float:left;margin-right: 10px;">
                    <?php echo sprintf('<a href="?page=%s&action=%s" class="button button-primary">' . __('Retrieve images', 'growtype-art') . '</a>', $_REQUEST['page'], 'retrieve-models') ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s" class="button button-primary" style="display: none;">' . __('Pull external images', 'growtype-art') . '</a>', $_REQUEST['page'], 'index-download-all-models-images') ?>
                    <?php echo sprintf('<a href="?page=%s&action=%s" class="button button-primary">' . __('Generate models', 'growtype-art') . '</a>', $_REQUEST['page'], 'generate-models') ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Set up items for display in the list table.
     *
     * Handles filtering of data, sorting, pagination, and any other data
     * manipulation required prior to rendering.
     *
     * @since 2.0.0
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $search_value = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';

        $paged = $this->get_pagenum();

        $items_per_page = $this->get_items_per_page('items_per_page', 20);
        $offset = ($paged - 1) * $items_per_page;

        $take_all_records = true;

        $args = array (
            'offset' => $offset,
            'limit' => $items_per_page,
            'search' => $search_value
        );

        if (isset($_REQUEST['orderby'])) {
            $args['orderby'] = $_REQUEST['orderby'];
        }

        if (isset($_REQUEST['order'])) {
            $args['order'] = $_REQUEST['order'];
        }

        if (isset($_REQUEST['model_is_in_bundle']) && $_REQUEST['model_is_in_bundle']) {
            $take_all_records = false;
            $bundle_ids = explode(',', get_option('growtype_art_bundle_ids'));

            $args['key'] = 'id';
            $args['values'] = $bundle_ids;
        }

        if (empty($search_value) && isset($_REQUEST['filter_models_action']) && !empty($_REQUEST['filter_models_action'])) {
            $take_all_records = false;
            $args['search'] = $_REQUEST['filter_models_action'];
        }

        $items = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE, [$args]);

        /**
         * Update missing values
         */
//        foreach ($items as $item) {
//            if (!empty(growtype_art_get_model_single_setting($item['id'], 'created_by_unique_hash'))) {
//                growtype_art_admin_update_model_settings($item['id'], [
//                    'created_by' => 'external_user',
//                ],
//                    ['created_by']);
//            } else {
//                growtype_art_admin_update_model_settings($item['id'], [
//                    'created_by' => 'admin',
//                ],
//                    ['created_by']);
//            }
//        }

        if (!$take_all_records) {
            $query_args = $args;
            $query_args['limit'] = null;
            $query_args['offset'] = 0;
            $total_items = count(Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE, [$query_args]));
        } else {
            $total_items = count(Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE));
        }

        $this->items = $items;

        $this->items_count = $total_items;

        $this->_column_headers = $this->get_column_info();

        $this->set_pagination_args(array (
            'total_items' => $this->items_count,
            "total_pages" => ceil($total_items / $items_per_page),
            'per_page' => $items_per_page,
        ));
    }

    /**
     * Specific columns.
     *
     * @return array
     * @since 2.0.0
     *
     */
    function get_columns()
    {
        return apply_filters('growtype_quiz_members_signup_columns', array (
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'growtype-art'),
            'in_bundle' => __('In bundle', 'growtype-art'),
            'prompt' => __('Prompt', 'growtype-art'),
            'negative_prompt' => __('Negative prompt', 'growtype-art'),
            'reference_id' => __('Reference id', 'growtype-art'),
            'slug' => __('Slug', 'growtype-art'),
            'stats' => __('Stats', 'growtype-art'),
            'featured_in' => __('Featured in', 'growtype-art'),
            'tags' => __('Tags', 'growtype-art'),
            'provider' => __('Provider', 'growtype-art'),
            'images' => __('Images', 'growtype-art'),
            'created_by' => __('Created by', 'growtype-art'),
            'created_by_unique_hash' => __('Character UNIQUE HASH', 'growtype-art'),
            'created_style' => __('Character style', 'growtype-art'),
            'is_private' => __('Is private', 'growtype-art'),
            'created_at' => __('Created at', 'growtype-art'),
            'updated_at' => __('Updated at', 'growtype-art'),
        ));
    }

    public function get_hidden_columns()
    {
        return array ();
    }

    /**
     * Specific bulk actions
     *
     * @since 2.0.0
     */
    public function get_bulk_actions()
    {
        $actions = array (
            'add-to-bundle' => __('Add to bundle', 'growtype-art'),
            'remove-from-bundle' => __('Remove from bundle', 'growtype-art'),
            'download-zip' => __('Download zip', 'growtype-art'),
        );

        if (current_user_can('delete_users')) {
            $actions['bulk_delete'] = __('Delete', 'growtype-art');
        }

        return $actions;
    }

    /**
     * @return void
     */
    public function no_items()
    {
        esc_html_e('No items found.', 'growtype-art');
    }

    /**
     * @return array[]
     */
    public function get_sortable_columns()
    {
        return array (
            'created_at' => array ('created_at', false),
            'updated_at' => array ('updated_at', false),
            'questions_amount' => array ('questions_amount', false),
        );
    }

    /**
     * @return void
     */
    public function display_rows()
    {
        $items = $this->items;

        $style = '';
        foreach ($items as $userid => $signup_object) {
            $style = (' class="alternate"' == $style) ? '' : ' class="alternate"';
            echo "\n\t" . $this->single_row($signup_object, $style);
        }
    }

    /**
     * @param $signup_object
     * @param $style
     * @param $role
     * @param $numposts
     * @return void
     */
    public function single_row($signup_object = null, $style = '', $role = '', $numposts = 0)
    {
        echo '<tr' . $style . ' id="model-' . esc_attr($signup_object['id']) . '" data-id="' . esc_attr($signup_object['id']) . '">';
        echo $this->single_row_columns($signup_object);
        echo '</tr>';
    }

    // Adding action links to column
    function column_id($item)
    {
        $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;

        $actions = array (
            'edit' => sprintf('<a href="?page=%s&action=%s&model=%s">' . __('Edit', 'growtype-art') . '</a>', $_REQUEST['page'], 'edit', $item['id']),
            'generate' => sprintf('<a href="?page=%s&action=%s&model=%s&paged=%s">' . __('Generate image', 'growtype-art') . '</a>', $_REQUEST['page'], 'index-generate-images', $item['id'], $paged),
            'duplicate-model' => sprintf('<a href="?page=%s&action=%s&model=%s&paged=%s">' . __('Duplicate model', 'growtype-art') . '</a>', $_REQUEST['page'], 'index-duplicate-model', $item['id'], $paged),
            'download-images' => sprintf('<a href="?page=%s&action=%s&model=%s&paged=%s">' . __('Pull external images', 'growtype-art') . '</a>', $_REQUEST['page'], 'index-download-model-images', $item['id'], $paged),
            'delete' => sprintf('<a href="?page=%s&action=%s&model_id=%s&_wpnonce=%s&paged=%s">' . __('Delete', 'growtype-art') . '</a>', $_REQUEST['page'], 'delete', $item['id'], wp_create_nonce(Growtype_Art_Admin::DELETE_NONCE), $paged),
        );

        return sprintf('%1$s %2$s', $item['id'], $this->row_actions($actions, true));
    }

    function column_created_by($row)
    {
        $model = growtype_art_get_model_details($row['id']);

        echo isset($model['settings']['created_by']) && !empty($model['settings']['created_by']) ? '<span style="color:red;">' . $model['settings']['created_by'] . '</span>' : 'admin';
    }

    function column_created_by_unique_hash($row)
    {
        $model = growtype_art_get_model_details($row['id']);

        echo isset($model['settings']['created_by_unique_hash']) && !empty($model['settings']['created_by_unique_hash']) ? '<span>' . $model['settings']['created_by_unique_hash'] . '</span>' : '';
    }

    function column_created_style($row)
    {
        $model = growtype_art_get_model_details($row['id']);

        echo isset($model['settings']['character_style']) && !empty($model['settings']['character_style']) ? '<span>' . $model['settings']['character_style'] . '</span>' : '';
    }

    function column_is_private($row)
    {
        $model = growtype_art_get_model_details($row['id']);

        echo isset($model['settings']['model_is_private']) && !empty($model['settings']['model_is_private']) ? '<span style="color:green;font-weight: bold;font-size:18px;">true</span>' : 'false';
    }

    function column_in_bundle($item)
    {
        $bundle_ids = explode(',', get_option('growtype_art_bundle_ids'));

        echo in_array($item['id'], $bundle_ids) ? '<span style="background: green;
    color: white;
    padding: 5px 20px;
    border-radius: 50px;
    position: relative;
    top: 10px;">Yes</span>' : 'No';
    }

    /**
     * @param $row
     * @return void
     */
    public function column_cb($row = null)
    {
        ?>
        <input type="checkbox" id="result_<?php echo intval($row['id']) ?>" name="model_id[]" value="<?php echo esc_attr($row['id']) ?>"/>
        <?php
    }

    /**
     * @param $row
     * @return void
     */
    public function column_featured_in($row = null)
    {
        $setting = growtype_art_get_model_single_setting($row['id'], 'featured_in');
        $meta_value = isset($setting['meta_value']) ? json_decode($setting['meta_value'], true) : [];

        echo Growtype_Art_Admin_Model_List_Table_Record::render_featured_in_select($meta_value);
    }

    /**
     * @param $row
     * @return void
     */
    public function column_tags($row = null)
    {
        $setting = growtype_art_get_model_single_setting($row['id'], 'tags');
        $meta_value = isset($setting['meta_value']) && !empty($setting['meta_value']) ? json_decode($setting['meta_value'], true) : [];
        $meta_value = !empty($meta_value) ? implode(',', $meta_value) : '';

        echo Growtype_Art_Admin_Model_List_Table_Record::render_textarea('settings[tags]', $meta_value);
    }

    /**
     * @param $row
     * @return void
     */
    public function column_provider($row = null)
    {
        $model = growtype_art_get_model_details($row['id']);
        $meta_value = isset($model['provider']) ? $model['provider'] : [];

        echo Growtype_Art_Admin_Model_List_Table_Record::render_provider_select($meta_value);
    }

    /**
     * @param $row
     * @return void
     */
    public function column_images($row = null)
    {
        $model_images = growtype_art_get_model_images_grouped($row['id'])['original'] ?? [];
        $model_images = array_filter($model_images, function ($file) {
            return strtolower($file['extension']) !== 'mp4';
        });
        $model_images = array_slice(array_reverse($model_images), 0, 3);
        ?>
        <div style="display: flex;flex-wrap: wrap;">
            <?php foreach ($model_images as $image) {
                $image_url = growtype_art_get_image_url($image['id']);
                ?>
                <div style="max-width: 200px;">
                    <img src="<?php echo $image_url ?>" alt="" style="max-width: 100%;">
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * @param $row
     * @return void
     */
    public function column_location($row = null)
    {
        $model_images = growtype_art_get_model_images_grouped($row['id'])['original'] ?? [];

        echo implode(',', array_unique(array_pluck($model_images, 'location')));
    }

    /**
     * @param $row
     * @return void
     */
    public function column_slug($row = null)
    {
        $model = growtype_art_get_model_details($row['id']);

        echo isset($model['settings']['slug']) ? $model['settings']['slug'] : '';
    }

    /**
     * @param $row
     * @return void
     */
    public function column_prompt($row = null)
    {
        $model = growtype_art_get_model_details($row['id']);

        $prompt = growtype_art_model_format_prompt($model['prompt'] ?? '', $row['id']);

        echo $prompt;
    }

    /**
     * @param $row
     * @return void
     */
    public function column_stats($row = null)
    {
        $stats = growtype_art_get_model_images_group_stats($row['id']);

        echo 'Total: ' . $stats['total'];
        echo '<br>';
        echo 'Nsfw: ' . $stats['nsfw'];
        echo '<br>';
        echo 'Is featured: ' . $stats['featured'];
        echo '<br>';
        echo 'Is cover: ' . $stats['cover'];
    }

    /**
     * @param $item
     * @param $column_name
     * @return mixed|void|null
     */
    function column_default($item = null, $column_name = '')
    {
        return apply_filters('growtype_quiz_result_custom_column', $item[$column_name] ?? '', $column_name, $item);
    }
}
