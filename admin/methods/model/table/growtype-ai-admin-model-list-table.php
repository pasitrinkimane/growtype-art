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
class Growtype_Ai_Admin_Result_List_Table extends WP_List_Table
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
    }

    function extra_tablenav($which)
    {
        if ($which == "top") { ?>
            <div style="display: inline-block;">
                <div class="actions-box" style="display: flex;gap: 10px;">
                    <?php echo sprintf('<a href="?page=%s&action=%s" class="button button-primary">' . __('Retrieve models', 'growtype-ai') . '</a>', $_REQUEST['page'], 'retrieve-images-all') ?>
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

        $hidden = array ();

        $search_value = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';

        $items_per_page = $this->get_items_per_page('items_per_page', 20);

        $paged = $this->get_pagenum();

        $args = array (
            'offset' => ($paged - 1) * $items_per_page,
            'limit' => $items_per_page,
            'search' => $search_value
        );

        if (isset($_REQUEST['orderby'])) {
            $args['orderby'] = $_REQUEST['orderby'];
        }

        if (isset($_REQUEST['order'])) {
            $args['order'] = $_REQUEST['order'];
        }

        $items = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE, [$args]);

        $total_items = count(Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE));

        $this->items = $items;

        $this->items_count = $total_items;

        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array ($columns, $hidden, $sortable);

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
            'id' => __('ID', 'growtype-ai'),
            'in_bundle' => __('In bundle', 'growtype-ai'),
            'prompt' => __('Prompt', 'growtype-ai'),
            'negative_prompt' => __('Negative prompt', 'growtype-ai'),
            'reference_id' => __('Reference id', 'growtype-ai'),
            'provider' => __('Provider', 'growtype-ai'),
            'images' => __('Images', 'growtype-ai'),
            'created_at' => __('Created at', 'growtype-ai'),
            'updated_at' => __('Updated at', 'growtype-ai'),
        ));
    }

    /**
     * Specific bulk actions
     *
     * @since 2.0.0
     */
    public function get_bulk_actions()
    {
        $actions = array (
            'add-to-bundle' => __('Add to bundle', 'growtype-ai'),
            'remove-from-bundle' => __('Remove from bundle', 'growtype-ai'),
        );

        if (current_user_can('delete_users')) {
            $actions['bulk_delete'] = __('Delete', 'growtype-ai');
        }

        return $actions;
    }

    /**
     * @return void
     */
    public function no_items()
    {
        esc_html_e('No items found.', 'growtype-ai');
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
        echo '<tr' . $style . ' id="signup-' . esc_attr($signup_object['id']) . '">';
        echo $this->single_row_columns($signup_object);
        echo '</tr>';
    }

    // Adding action links to column
    function column_id($item)
    {
        $actions = array (
            'edit' => sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s">' . __('Edit', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'edit', $item['id']),
            'delete' => sprintf('<a href="?post_type=%s&page=%s&action=%s&item=%s&_wpnonce=%s">' . __('Delete', 'growtype-ai') . '</a>', Growtype_Ai_Admin::POST_TYPE, $_REQUEST['page'], 'delete', $item['id'], wp_create_nonce(Growtype_Ai_Admin::DELETE_NONCE)),
        );

        return sprintf('%1$s %2$s', $item['id'], $this->row_actions($actions, true));
    }

    function column_in_bundle($item)
    {
        $bundle_ids = explode(',', get_option('growtype_ai_bundle_ids'));

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
        <input type="checkbox" id="result_<?php echo intval($row['id']) ?>" name="items[]" value="<?php echo esc_attr($row['id']) ?>"/>
        <?php
    }

    /**
     * @param $row
     * @return void
     */
    public function column_images($row = null)
    {
        $model_images = growtype_ai_get_model_images($row['id']);
        $model_images = array_slice($model_images, 0, 6);

        ?>
        <div style="display: flex;flex-wrap: wrap;">
            <?php foreach ($model_images as $image) { ?>
                <div style="max-width: 50px;">
                    <img src="https://res.cloudinary.com/dmm4mlnmq/image/upload/v1677258489/<?php echo $image['folder'] ?>/<?php echo $image['name'] ?>.<?php echo $image['extension'] ?>" alt="" style="max-width: 100%;">
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * @param $item
     * @param $column_name
     * @return mixed|void|null
     */
    function column_default($item = null, $column_name = '')
    {
        return apply_filters('growtype_quiz_result_custom_column', $item[$column_name], $column_name, $item);
    }
}
