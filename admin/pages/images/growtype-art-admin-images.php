<?php

class Growtype_Art_Admin_Images
{

    public function __construct()
    {
        $this->load_partials();

        add_action('admin_menu', array ($this, 'items_tab_init'));

        /**
         * Ajax
         */
        add_action('wp_ajax_growtype_art_admin_remove_image', array ($this, 'remove_image_callback'));
        add_action('wp_ajax_growtype_art_admin_update_image', array ($this, 'update_image_callback'));
        add_action('wp_ajax_growtype_art_admin_compress_image', array ($this, 'compress_image_callback'));
    }

    private function load_partials()
    {
        foreach (glob(__DIR__ . '/partials/*.php') as $partial) {
            require_once $partial;

            $filename = basename($partial, '.php');
            if (strpos($filename, 'class-') === 0) {
                $class_name = implode('_', array_map('ucfirst', explode('_',
                    str_replace('-', '_', substr($filename, strlen('class-')))
                )));
                if (class_exists($class_name)) {
                    new $class_name();
                }
            }
        }
    }

    function remove_image_callback()
    {
        $image_id = $_POST['image_id'];

        /**
         * Delete image in assigned posts
         */
        $image_details = growtype_art_get_image_model_details($image_id);

        if (isset($image_details['settings']['post_type_to_collect_data_from']) && !empty($image_details['settings']['post_type_to_collect_data_from'])) {
            $posts = get_posts([
                'post_type' => $image_details['settings']['post_type_to_collect_data_from'],
                'post_status' => 'any',
                'numberposts' => -1
            ]);

            foreach ($posts as $post) {
                $growtype_art_images_ids = get_post_meta($post->ID, 'growtype_art_images_ids', true);
                $growtype_art_images_ids = !empty($growtype_art_images_ids) ? json_decode($growtype_art_images_ids, true) : [];

                if (!empty($growtype_art_images_ids)) {
                    if (($key = array_search($image_id, $growtype_art_images_ids)) !== false) {
                        unset($growtype_art_images_ids[$key]);
                    }

                    $growtype_art_images_ids = array_values($growtype_art_images_ids);

                    update_post_meta($post->ID, 'growtype_art_images_ids', json_encode($growtype_art_images_ids));
                }
            }
        }

        Growtype_Art_Crud::delete_image($image_id);

        do_action('growtype_art_model_image_delete', $image_id);

        return wp_send_json(
            [
                'message' => __('Success', 'growtype')
            ], 200);
    }

    function update_image_callback()
    {
        $image_id = $_POST['image_id'];
        $value = $_POST['value'];
        $name = $_POST['name'];

        $property_to_update = explode('[', rtrim($name, ']'));
        $settings_exists = isset($property_to_update[0]) && $property_to_update[0] === 'settings' ? true : false;

        if (is_array($value)) {
            $value = json_encode($value);
        } else {

            if ($value === 'true') {
                $value = '1';
            } elseif ($value === 'false') {
                $value = '0';
            }
        }

        if ($settings_exists) {
            $property_to_update = $property_to_update[1];

            Growtype_Art_Database_Crud::update_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE,
                [
                    [
                        'key' => 'image_id',
                        'values' => [$image_id]
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

        do_action('growtype_art_model_image_update', $image_id);

        return wp_send_json(
            [
                'message' => __('Updated', 'growtype')
            ], 200);
    }

    function compress_image_callback()
    {
        $image_id = $_POST['image_id'];

        growtype_art_compress_existing_image($image_id);

        return wp_send_json(
            [
                'message' => __('Success', 'growtype')
            ], 200);
    }

    /**
     * Create the All Users / Profile > Edit Profile and All Users Signups submenus.
     *
     * @since 2.0.0
     *
     */
    public function items_tab_init()
    {
        add_submenu_page(
            'growtype-art',
            'Images',
            'Images',
            'manage_options',
            'growtype-art',
            array ($this, 'growtype_art_result_callback'),
            100
        );
    }

    /**
     * Display callback for the submenu page.
     */
    function growtype_art_result_callback()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $random = isset($_GET['random']) ? true : false;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 200;
        $mode = isset($_GET['mode']) ? $_GET['mode'] : 'grid';
        $content_type = isset($_GET['content_type']) ? $_GET['content_type'] : '';

        $query_args = [
            [
                'limit' => $limit,
                'offset' => $offset,
                'orderby' => 'id',
            ]
        ];

        if (!empty($content_type)) {
            $extensions = [];
            if ($content_type === 'image') {
                $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            } elseif ($content_type === 'video') {
                $extensions = ['mp4', 'webm', 'mov', 'm4v'];
            } elseif ($content_type === 'audio') {
                $extensions = ['mp3', 'wav', 'ogg', 'm4a'];
            }

            if (!empty($extensions)) {
                $query_args[0]['key'] = 'extension';
                $query_args[0]['values'] = $extensions;
            }
        }

        if ($random) {
            $query_args[0]['orderby'] = 'rand()';
        }

        $images = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGES_TABLE, $query_args);

        if ($mode === 'list') { ?>
            <style>
                .b-imgs {
                    grid-template-columns: repeat(1, minmax(0, 1fr));
                }

                .b-imgs .image {
                    display: grid;
                    gap: 20px;
                    grid-template-columns: 1fr 3fr;
                }

                .b-imgs .image-preview {
                    max-width: 300px;
                }

                .b-imgs .image-details-metrics {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                }
            </style>
        <?php } ?>

        <style>
            .growtype-ajax-button.is-active {
                background: #46b450 !important;
                border-color: #46b450 !important;
                color: #fff !important;
                box-shadow: none !important;
            }

            .growtype-ajax-button.is-loading {
                opacity: 0.5;
                pointer-events: none;
            }
        </style>

        <?php
        echo '<div class="wrap">';
        ?>

        <div class="wp-filter" style="display: flex;align-items: center;">
            <div class="filter-items" style="display: flex; gap: 20px; align-items: center;">
                <input type="hidden" name="mode" value="list">
                <div class="view-switch">
                    <a href="<?= add_query_arg(['mode' => 'list']) ?>" class="view-list <?= $mode === 'list' ? 'current' : '' ?>" id="view-switch-list" aria-current="page"><span class="screen-reader-text">List view</span></a>
                    <a href="<?= add_query_arg(['mode' => 'grid']) ?>" class="view-grid <?= $mode === 'grid' ? 'current' : '' ?>" id="view-switch-grid"><span class="screen-reader-text">Grid view</span></a>
                </div>

                <div class="content-type-filter" style="display: flex; gap: 10px;">
                    <a href="<?= remove_query_arg('content_type') ?>" class="button <?= empty($content_type) ? 'button-primary' : 'button-secondary' ?>">All</a>
                    <a href="<?= add_query_arg(['content_type' => 'image']) ?>" class="button <?= $content_type === 'image' ? 'button-primary' : 'button-secondary' ?>">Images</a>
                    <a href="<?= add_query_arg(['content_type' => 'video']) ?>" class="button <?= $content_type === 'video' ? 'button-primary' : 'button-secondary' ?>">Videos</a>
                    <a href="<?= add_query_arg(['content_type' => 'audio']) ?>" class="button <?= $content_type === 'audio' ? 'button-primary' : 'button-secondary' ?>">Audio</a>
                </div>
            </div>

            <div style="display: flex;gap:10px;margin-left: auto;margin-left: 10px;margin-right: auto;">
                <a href="/wp/wp-admin/admin.php?page=growtype-art&offset=500&random=1">RANDOM</a>
            </div>

            <div class="bulk-actions" style="display: flex;gap: 10px;margin-left: 10px;align-items: center;">
                <div class="checkbox-group" style="display: flex;gap: 10px;">
                    <div class="checkbox-item">
                        <input type="checkbox" id="all_nsfw" name="all_nsfw">
                        <label for="all_nsfw">All Erotic</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="all_nudity" name="all_nudity">
                        <label for="all_nudity">All Nudity</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="all_porn" name="all_porn">
                        <label for="all_porn">All Porn</label>
                    </div>
                </div>
                <button type="button" class="button button-primary bulk-apply">Apply</button>
            </div>

            <script>
                jQuery('.bulk-apply').click(function (e) {
                    if (!confirm('Are you sure you want to change the status for these images?')) {
                        return;
                    }

                    let totalDelay = 0;
                    
                    jQuery('.bulk-actions input[type="checkbox"]:checked').each(function() {
                        let name = jQuery(this).attr('name');
                        let selected_key = name.replace('all_', '');

                        // Collect all the checkboxes that need to be clicked
                        let checkboxes = jQuery('input[name^="settings[' + selected_key + ']"]');

                        // Iterate over each checkbox with a delay between each click
                        checkboxes.each(function (index, element) {
                            setTimeout(function () {
                                jQuery(element).click();
                            }, totalDelay);
                            totalDelay += 300;
                        });
                    });
                });
            </script>
        </div>

        <?php
        echo '<div class="b-imgs" style="gap: 10px;display: grid;">';
        foreach ($images as $image) { ?>
            <?= $this->preview_image($image['id']); ?>
        <?php }
        echo '</div>';

        echo '</div>';

        $current_offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

        $count_params = null;
        if (!empty($content_type)) {
            $count_params = $query_args;
        }

        $total_items = Growtype_Art_Database_Crud::count_records(Growtype_Art_Database::IMAGES_TABLE, $count_params);

        echo Growtype_Art_Admin_Pages::render_pagination('growtype-art', $total_items, $current_offset, $limit);

        echo self::image_delete_ajax();
    }

    /**
     * Init record related actions
     */
    function process_actions()
    {

    }

    public static function preview_image($image_id)
    {
        $image = growtype_art_get_image_details($image_id);
        return self::preview_image_from_data($image, $image_id);
    }

    public static function preview_image_from_data($image, $image_id = null)
    {
        return Growtype_Art_Image_Preview::render($image, $image_id);
    }

    public static function image_delete_ajax()
    {
        ?>
        <script>
            $ = jQuery;

            /**
             * Unified AJAX Action Handler
             */
            $(document).on('click', '.growtype-ajax-button', function (e) {
                e.preventDefault();
                let $btn = $(this);
                if ($btn.hasClass('is-loading')) return;

                let action = $btn.attr('data-action');
                let confirmMsg = $btn.attr('data-confirm');
                let image = $btn.closest('.image');
                let imageId = $btn.attr('data-id') || image.attr('data-id');

                if (confirmMsg && !confirm(confirmMsg)) return;

                // Success Action parameters
                let successAction = $btn.attr('data-success-action'); // remove, toggle, addClass:name
                let className = $btn.attr('data-class');
                let name = $btn.attr('data-name');

                // Prepare Data
                let ajaxData = {
                    action: action,
                    image_id: imageId
                };

                if (successAction === 'toggle') {
                    ajaxData.name = name;
                    ajaxData.value = image.hasClass(className) ? false : true;
                }

                $btn.addClass('is-loading').css('opacity', '0.5');
                if (successAction === 'remove') image.css('opacity', '0.3');

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: ajaxData,
                    success: function (res) {
                        $btn.removeClass('is-loading').css('opacity', '1');

                        if (successAction === 'remove') {
                            image.fadeOut(300, function () {
                                $(this).remove();
                            });
                        } else if (successAction === 'toggle') {
                            if (image.hasClass(className)) {
                                image.removeClass(className);
                                $btn.removeClass('is-active');
                                $btn.text($btn.attr('data-off'));
                            } else {
                                image.addClass(className);
                                $btn.addClass('is-active');
                                $btn.text($btn.attr('data-on'));
                            }
                        } else if (successAction && successAction.startsWith('addClass:')) {
                            let cls = successAction.split(':')[1];
                            image.addClass(cls);
                            $btn.addClass('is-active');
                            if ($btn.attr('data-success-text')) $btn.text($btn.attr('data-success-text'));
                        }

                        if ($btn.attr('data-notification') !== 'false') {
                            showNotification(res.data && res.data.message ? res.data.message : 'Success');
                        }
                    },
                    error: function (err) {
                        $btn.removeClass('is-loading').css('opacity', '1');
                        image.css('opacity', '1');
                        console.error(err);
                        alert('Something went wrong. Please check the console.');
                    }
                });
            });

            /**
             * Checkbox handler
             */
            $('input[type="checkbox"][name^="settings["]').click(function (e) {
                let image = $(this).closest('.image');
                let name = $(this).attr('name');
                let isChecked = $(this).is(':checked');
                let match = name.match(/\[(.*?)\]/);
                let extractedValue = match ? match[1] : '';

                if (isChecked) image.addClass('is-' + extractedValue);
                else image.removeClass('is-' + extractedValue);

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_update_image',
                        image_id: image.attr('data-id'),
                        name: name,
                        value: isChecked,
                    },
                    success: function (res) {
                        showNotification('Updated setting')
                    }
                });
            });

            function showNotification($message) {
                $('.status-notification').remove()
                $('body').append('<div class="status-notification" style="position: fixed;top: 50px;right: 25px;background: green;padding: 10px;border-radius: 10px;color: white;z-index: 9999;"><b>' + $message + '</b></div>');
                $('.status-notification').hide().fadeIn(300).delay(2000).fadeOut(300, function () {
                    $(this).remove();
                });
            }
        </script>
        <?php
    }

}
