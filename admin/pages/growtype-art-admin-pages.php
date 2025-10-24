<?php

class Growtype_Art_Admin_Pages
{
    public function __construct()
    {
        add_action('admin_menu', array ($this, 'admin_menu_pages'));

        $this->load_pages();
    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu_pages()
    {
        /**
         * Main
         */
        add_menu_page(
            __('Dashboard', 'growtype-art'),
            __('Growtype Art', 'growtype-art'),
            'manage_options',
            'growtype-art',
            array ($this, 'growtype_art')
        );
    }

    function growtype_art()
    {

    }

    public function load_pages()
    {
        /**
         * Images
         */
        require_once 'images/growtype-art-admin-images.php';
        new Growtype_Art_Admin_Images();

        /**
         * Feed
         */
        require_once 'feed/growtype-art-admin-feed.php';
        new Growtype_Art_Admin_Feed();

        /**
         * Models
         */
        require_once 'models/growtype-art-admin-models.php';
        new Growtype_Art_Admin_Models();

        /**
         * Settings
         */
        require_once 'settings/growtype-art-admin-settings.php';
        new Growtype_Art_Admin_Settings();
    }

    public static function render_pagination($page, $total_items, $current_offset, $limit)
    {
        ob_start();
        ?>
        <div class="pagination">
            <?php
            $total_pages = ceil($total_items / $limit);

            $current_page = (int)floor($current_offset / $limit);
            $visible_pages = 10;

            $start_page = max(0, $current_page - floor($visible_pages / 2));
            $end_page = min($total_pages, $start_page + $visible_pages);

            $start_page = max(0, $end_page - $visible_pages);

            // Display the "Previous" link if applicable
            if ($start_page > 0) {
                $prev_offset = max(0, $current_offset - ($limit * $visible_pages));
                $prev_url = self::generate_pagination_url($prev_offset, $limit, $page);
                echo "<a class='pagination-single' href='$prev_url'>...</a>";
            }

            for ($i = $start_page; $i < $end_page; $i++) {
                $offset = $i * $limit;
                $pagination_url = self::generate_pagination_url($offset, $limit, $page);
                $active_class = ($offset == $current_offset) ? 'active' : '';
                echo "<a class='pagination-single $active_class' href='$pagination_url'>Page " . ($i + 1) . "</a>";
            }

            if ($end_page < $total_pages) {
                $next_offset = min($total_items, $current_offset + ($limit * $visible_pages));
                $next_url = self::generate_pagination_url($next_offset, $limit, $page);
                echo "<a class='pagination-single' href='$next_url'>...</a>";
            }
            ?>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function generate_pagination_url($offset, $limit, $page = 'growtype-art')
    {
        // Get the base admin URL
        $base_url = admin_url('admin.php');

        // Get the current query parameters
        $current_params = $_GET;

        // Set the page, offset, and limit values
        $current_params['page'] = $page;
        $current_params['offset'] = $offset;
        $current_params['limit'] = $limit;

        // Build the query string with the merged parameters
        $query_string = http_build_query($current_params);

        // Construct the full URL
        return "{$base_url}?{$query_string}";
    }
}
