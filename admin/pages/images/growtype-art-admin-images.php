<?php

class Growtype_Art_Admin_Images
{
    const IMAGE_VERSION = '1.1.7';

    public function __construct()
    {
        add_action('admin_menu', array ($this, 'items_tab_init'));

        /**
         * Ajax
         */
        add_action('wp_ajax_growtype_art_admin_remove_image', array ($this, 'remove_image_callback'));
        add_action('wp_ajax_growtype_art_admin_update_image', array ($this, 'update_image_callback'));
        add_action('wp_ajax_growtype_art_admin_compress_image', array ($this, 'compress_image_callback'));
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

        $query_args = [
            [
                'limit' => $limit,
                'offset' => $offset,
                'orderby' => 'id',
            ]
        ];

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

        <?php
        echo '<div class="wrap">';
        ?>

        <div class="wp-filter" style="display: flex;align-items: center;">
            <div class="filter-items">
                <input type="hidden" name="mode" value="list">
                <div class="view-switch">
                    <a href="<?= add_query_arg(['mode' => 'list']) ?>" class="view-list <?= $mode === 'list' ? 'current' : '' ?>" id="view-switch-list" aria-current="page"><span class="screen-reader-text">List view</span></a>
                    <a href="<?= add_query_arg(['mode' => 'grid']) ?>" class="view-grid <?= $mode === 'grid' ? 'current' : '' ?>" id="view-switch-grid"><span class="screen-reader-text">Grid view</span></a>
                </div>
            </div>

            <div class="bulk-actions" style="display: flex;gap: 10px;margin-left: 10px;">
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
            </div>

            <script>
                jQuery('.bulk-actions input[type="checkbox"]').click(function (e) {
                    let name = jQuery(this).attr('name');
                    let isChecked = jQuery(this).is(':checked');
                    let selected_key = name.replace('all_', '');

                    // Collect all the checkboxes that need to be clicked
                    let checkboxes = jQuery('input[name^="settings[' + selected_key + ']"]');

                    // Iterate over each checkbox with a delay between each click
                    checkboxes.each(function (index, element) {
                        setTimeout(function () {
                            jQuery(element).click();
                        }, index * 300); // 500ms delay for each checkbox
                    });
                });
            </script>

            <div style="display: flex;gap:10px;margin-left: auto;">
                <a href="/wp/wp-admin/admin.php?page=growtype-art&offset=500&random=1">RANDOM</a>
            </div>
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
        $total_items = Growtype_Art_Database_Crud::table_total_records_amount('wp_growtype_art_images');

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
        return self::preview_image_from_data($image);
    }

    public static function preview_image_from_data($image)
    {
        if (empty($image)) {
            ob_start();

            ?>
            <div class="image" data-id="<?= $image['id'] ?>">
                Image doesnt exist
                <div style="display:flex;flex-wrap: wrap;gap: 15px;flex-direction: column;">
                    <a href="#" class="button button-primary button-delete" data-id="<?= $image_id ?>"><?= __('Delete', 'growtype-art') ?></a>
                </div>
            </div>
            <?php

            return ob_get_clean();
        }

        $prompt = isset($image['settings']['prompt']) ? $image['settings']['prompt'] : '';
        $caption = isset($image['settings']['caption']) ? $image['settings']['caption'] : '';
        $alt_text = isset($image['settings']['alt_text']) ? $image['settings']['alt_text'] : '';
        $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : '';
        $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : '';
        $tags = !empty($tags) ? implode(', ', $tags) : '';

        $compressed = isset($image['settings']['compressed']) ? true : false;
        $real_esrgan = isset($image['settings']['real_esrgan']) ? true : false;

        $img_url = growtype_art_build_public_image_url($image);

        $provider = isset($image['settings']['provider']) && !empty($image['settings']['provider']) ? $image['settings']['provider'] : '-';
        $main_colors = isset($image['settings']['main_colors']) && !empty($image['settings']['main_colors']) && !empty(json_decode($image['settings']['main_colors'], true)) ? implode(',', json_decode($image['settings']['main_colors'], true)) : '';

        $is_featured = isset($image['settings']['is_featured']) ? $image['settings']['is_featured'] : false;
        $is_cover = isset($image['settings']['is_cover']) ? $image['settings']['is_cover'] : false;
        $is_compressed = isset($image['settings']['compressed']) ? $image['settings']['compressed'] : false;

        ob_start();

        if (!empty($img_url)) { ?>
            <div class="image <?= $is_featured ? 'is-featured' : '' ?> <?= $is_cover ? 'is-cover' : '' ?> <?= isset($image['settings']['nsfw']) && $image['settings']['nsfw'] ? 'is-nsfw' : '' ?> <?= isset($image['settings']['nudity']) && $image['settings']['nudity'] ? 'is-nudity' : '' ?> <?= isset($image['settings']['porn']) && $image['settings']['porn'] ? 'is-porn' : '' ?> <?= isset($image['settings']['private']) && $image['settings']['private'] ? 'is-private' : '' ?>" data-id="<?= $image['id'] ?>">
                <div class="image-preview">
                    <a href="<?php echo growtype_art_image_get_alternative_format($img_url) ?>?v=<?php echo self::IMAGE_VERSION ?>" target="_blank" style="min-height: 100px;width: 100%;display: flex;margin-bottom: 10px;">
                        <?php if (in_array($image['extension'], ['jpg', 'jpeg', 'png', 'webp'])) { ?>
                            <img src="<?php echo growtype_art_image_get_alternative_format($img_url) ?>?v=<?php echo self::IMAGE_VERSION ?>" alt="" style="max-width: 100%;">
                        <?php } else { ?>
                            <video width="100%" height="100%" controls loop muted>
                                <source type="video/mp4" src="<?php echo $img_url ?>">
                            </video>
                        <?php } ?>
                    </a>
                </div>
                <div class="image-details">
                    <div style="display:flex;flex-wrap: wrap;gap: 15px;">
                        <a href="#" class="button button-primary button-delete" data-id="<?= $image['id'] ?>"><?= __('Delete', 'growtype-art') ?></a>
                        <a style="display: none" href="<?= sprintf('/wp/wp-admin/admin.php?page=growtype-art-models&action=generate-image-content&model=%s&image=%s', growtype_art_get_image_model_details($image['id'])['id'], $image['id']) ?>" class="button button-secondary button-regenerate" data-id="<?= $image['id'] ?>"><?= __('Regenerate description', 'growtype-art') ?></a>
                        <a href="#" class="button button-secondary button-compress" data-id="<?= $image['id'] ?>" style="<?= $is_compressed ? 'background: green;color: white;' : '' ?>"><?= $is_compressed ? __('Is compressed!', 'growtype-art') : __('Compress photo', 'growtype-art') ?></a>
                        <a style="display: none" href="<?= sprintf('/wp/wp-admin/admin.php?page=growtype-art-models&action=generate-images&model=%s&image=%s', growtype_art_get_image_model_details($image['id'])['id'], $image['id']) ?>" target="_blank" class="button button-secondary button-generate" data-id="<?= $image['id'] ?>"><?= __('Generate new image', 'growtype-art') ?></a>
                        <a href="#" class="button button-secondary button-featured" data-id="<?= $image['id'] ?>"><?= $is_featured ? __('Is featured!', 'growtype-art') : __('Feature', 'growtype-art') ?></a>
                        <a href="#" class="button button-secondary button-cover" data-id="<?= $image['id'] ?>"><?= $is_cover ? __('Is cover photo!', 'growtype-art') : __('Cover photo', 'growtype-art') ?></a>
                    </div>
                    <?php
                    if ($real_esrgan) {
                        echo '<span style="color: green;display:inline-block;padding-top: 20px;">Upscaled</span>';
                    }
                    ?>
                    <div class="image-details-metrics">
                        <div>
                            <p><b>Name:</b> <?php echo $image['name'] ?? '-' ?></p>
                            <p><b>Provider:</b> <?php echo $provider ?></p>
                            <p style="display: none;"><b>Main Colors:</b> <?php echo $main_colors ?></p>
                            <p><b>Prompt:</b> <?php echo $prompt ?></p>
                            <p><b>Caption:</b> <?php echo $caption ?></p>
                            <p><b>Alt text:</b> <?php echo $alt_text ?></p>
                        </div>
                        <div>
                            <p style="display: none;"><b>Tags:</b> <?php echo $tags ?></p>
                            <p><b>Model id:</b> <?php echo sprintf('<a href="?page=%s&action=%s&model=%s">' . $image['model_id'] . '</a>', Growtype_Art_Admin::MODELS_PAGE_NAME, 'edit', $image['model_id']) ?></p>
                            <p><b>Image id:</b> <?php echo $image['id'] ?></p>
                            <?php if (isset($image['settings']['id'])) { ?>
                                <p><b>External image id:</b> <?php echo $image['settings']['id'] ?></p>
                            <?php } ?>
                            <?php if (isset($image['settings']['model_id'])) { ?>
                                <p><b>External model id:</b> <?php echo $image['settings']['model_id'] ?></p>
                            <?php } ?>
                            <?php if (isset($image['settings']['modelId'])) { ?>
                                <p><b>External model id:</b> <?php echo $image['settings']['modelId'] ?></p>
                            <?php } ?>
                            <p><b>EROTIC(NSFW):</b> <input type="checkbox" name="settings[nsfw]" <?php echo checked($image['settings']['nsfw'] ?? false) ?>/></p>
                            <p><b>NUDITY:</b> <input type="checkbox" name="settings[nudity]" <?php echo checked($image['settings']['nudity'] ?? false) ?>/></p>
                            <p><b>PORN:</b> <input type="checkbox" name="settings[porn]" <?php echo checked($image['settings']['porn'] ?? false) ?>/></p>
                            <hr>
                            <p><b>PRIVATE:</b> <input type="checkbox" name="settings[private]" <?php echo checked($image['settings']['private'] ?? false) ?>/></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php }

        return ob_get_clean();
    }

    public static function image_delete_ajax()
    {
        ?>
        <script>
            $ = jQuery;

            $('.button-delete').click(function (e) {
                e.preventDefault();

                let image = $(this).closest('.image');

                image.hide()

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_remove_image',
                        image_id: image.attr('data-id'),
                    },
                    success: function (res) {
                        image.remove();
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            })

            $('input[type="checkbox"][name^="settings["]').click(function (e) {
                let image = $(this).closest('.image');
                let isChecked = $(this).is(':checked');
                let name = $(this).attr('name');
                let match = name.match(/\[(.*?)\]/);
                let extractedValue = match ? match[1] : '';

                if (isChecked) {
                    image.addClass('is-' + extractedValue)
                } else {
                    image.removeClass('is-' + extractedValue)
                }

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
                        showNotification('Updated content')
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            });

            function showNotification($message) {
                $('body').append('<div class="status-notification" style="position: fixed;top: 50px;right: 25px;background: green;padding: 10px;border-radius: 10px;color: white;display: none;"><b>' + $message + '</b></div>');
                $('.status-notification').fadeIn(300)
                setTimeout(function () {
                    $('.status-notification').fadeOut(300).promise().done(function () {
                        $('.status-notification').remove()
                    })
                }, 2000)
            }

            $('.button-featured').click(function (e) {
                e.preventDefault();

                let image = $(this).closest('.image');
                let btn = $(this);

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_update_image',
                        image_id: image.attr('data-id'),
                        name: 'settings[is_featured]',
                        value: image.hasClass('is-featured') ? false : true,
                    },
                    success: function (res) {
                        if (image.hasClass('is-featured')) {
                            image.removeClass('is-featured')
                            btn.text('Feature')
                        } else {
                            image.addClass('is-featured')
                            btn.text('Is featured!')
                        }
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            })

            $('.button-cover').click(function (e) {
                e.preventDefault();

                let image = $(this).closest('.image');
                let btn = $(this);

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_update_image',
                        image_id: image.attr('data-id'),
                        name: 'settings[is_cover]',
                        value: image.hasClass('is-cover') ? false : true,
                    },
                    success: function (res) {
                        if (image.hasClass('is-cover')) {
                            image.removeClass('is-cover')
                            btn.text('Cover photo')
                        } else {
                            image.addClass('is-cover')
                            btn.text('Is cover photo!')
                        }
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            });

            $('.button-compress').click(function (e) {
                e.preventDefault();

                let image = $(this).closest('.image');
                let btn = $(this);

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_art_admin_compress_image',
                        image_id: image.attr('data-id')
                    },
                    success: function (res) {
                        image.addClass('is-compressed')
                        btn.text('Is compressed!')
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            });
        </script>
        <?php
    }
}


