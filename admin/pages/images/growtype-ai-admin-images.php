<?php

class Growtype_Ai_Admin_Images
{
    const IMAGE_VERSION = '1.1.1';

    public function __construct()
    {
        add_action('admin_menu', array ($this, 'items_tab_init'));

        /**
         * Ajax
         */
        add_action('wp_ajax_growtype_ai_admin_remove_image', array ($this, 'remove_image_callback'));
        add_action('wp_ajax_growtype_ai_admin_update_image', array ($this, 'update_image_callback'));
        add_action('wp_ajax_growtype_ai_admin_compress_image', array ($this, 'compress_image_callback'));
    }

    function remove_image_callback()
    {
        $image_id = $_POST['image_id'];

        /**
         * Delete image in assigned posts
         */
        $image_details = growtype_ai_get_image_model_details($image_id);

        if (isset($image_details['settings']['post_type_to_collect_data_from']) && !empty($image_details['settings']['post_type_to_collect_data_from'])) {
            $posts = get_posts([
                'post_type' => $image_details['settings']['post_type_to_collect_data_from'],
                'post_status' => 'any',
                'numberposts' => -1
            ]);

            foreach ($posts as $post) {
                $growtype_ai_images_ids = get_post_meta($post->ID, 'growtype_ai_images_ids', true);
                $growtype_ai_images_ids = !empty($growtype_ai_images_ids) ? json_decode($growtype_ai_images_ids, true) : [];

                if (!empty($growtype_ai_images_ids)) {
                    if (($key = array_search($image_id, $growtype_ai_images_ids)) !== false) {
                        unset($growtype_ai_images_ids[$key]);
                    }

                    $growtype_ai_images_ids = array_values($growtype_ai_images_ids);

                    update_post_meta($post->ID, 'growtype_ai_images_ids', json_encode($growtype_ai_images_ids));
                }
            }
        }

        Growtype_Ai_Crud::delete_image($image_id);

        do_action('growtype_ai_model_image_delete', $image_id);

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

            Growtype_Ai_Database_Crud::update_records(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE,
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

        do_action('growtype_ai_model_image_update', $image_id);

        return wp_send_json(
            [
                'message' => __('Updated', 'growtype')
            ], 200);
    }

    function compress_image_callback()
    {
        $image_id = $_POST['image_id'];

        growtype_ai_compress_existing_image($image_id);

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
            'growtype-ai',
            'Images',
            'Images',
            'manage_options',
            'growtype-ai',
            array ($this, 'growtype_ai_result_callback'),
            100
        );
    }

    /**
     * Display callback for the submenu page.
     */
    function growtype_ai_result_callback()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $random = isset($_GET['random']) ? true : false;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 200;

        $query_args = [
            [
                'limit' => $limit,
                'offset' => $offset
            ]
        ];

        if ($random) {
            $query_args[0]['orderby'] = 'rand()';
        }

        $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE, $query_args);

        ?>
        <p><b>RANDOM</b></p>
        <a href="https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai&offset=500&random=1" style="padding-bottom: 10px;display: inline-block;">https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai&offset=500&random=1</a>
        <p><b>OFFSET</b></p>
        <a href="https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai&offset=450&limit=100" style="padding-bottom: 10px;display: inline-block;">https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai&offset=450&limit=100</a>
        <?php
        echo '<div class="b-imgs wrap" style="gap: 10px;display: grid;">';

        foreach ($images as $image) {
            echo $this->preview_image($image['id']);
        }

        echo '</div>';

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
        $image = growtype_ai_get_image_details($image_id);

        if (empty($image)) {
            ob_start();

            ?>
            <div class="image" data-id="<?= $image_id ?>">
                Image doesnt exist
                <div style="display:flex;flex-wrap: wrap;gap: 15px;flex-direction: column;">
                    <a href="#" class="button button-primary button-delete" data-id="<?= $image_id ?>"><?= __('Delete', 'growtype-ai') ?></a>
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

        $img_url = growtype_ai_get_image_url($image['id']);

        $main_colors = isset($image['settings']['main_colors']) && !empty($image['settings']['main_colors']) && !empty(json_decode($image['settings']['main_colors'], true)) ? implode(',', json_decode($image['settings']['main_colors'], true)) : '';

        $is_featured = isset($image['settings']['is_featured']) ? $image['settings']['is_featured'] : false;
        $is_cover = isset($image['settings']['is_cover']) ? $image['settings']['is_cover'] : false;
        $is_compressed = isset($image['settings']['compressed']) ? $image['settings']['compressed'] : false;

        ob_start();

        if (!empty($img_url)) { ?>
            <div class="image <?= $is_featured ? 'is-featured' : '' ?> <?= $is_cover ? 'is-cover' : '' ?> <?= isset($image['settings']['nsfw']) && $image['settings']['nsfw'] ? 'is-nsfw' : '' ?>" data-id="<?= $image['id'] ?>">
                <a href="<?php echo $img_url ?>?v=<?php echo self::IMAGE_VERSION ?>" target="_blank" style="min-height: 100px;width: 100%;display: flex;margin-bottom: 10px;">
                    <?php if (in_array($image['extension'], ['jpg', 'png'])) { ?>
                        <img src="<?php echo $img_url ?>?v=<?php echo self::IMAGE_VERSION ?>" alt="" style="max-width: 100%;">
                    <?php } else { ?>
                        <video width="100%" height="100%" controls autoplay loop>
                            <source type="video/mp4" src="<?php echo $img_url ?>">
                        </video>
                    <?php } ?>
                </a>
                <div style="display:flex;flex-wrap: wrap;gap: 15px;flex-direction: column;">
                    <a href="#" class="button button-primary button-delete" data-id="<?= $image['id'] ?>"><?= __('Delete', 'growtype-ai') ?></a>
                    <a href="<?= sprintf('/wp/wp-admin/admin.php?page=growtype-ai-models&action=generate-image-content&model=%s&image=%s', growtype_ai_get_image_model_details($image['id'])['id'], $image['id']) ?>" class="button button-secondary button-regenerate" data-id="<?= $image['id'] ?>"><?= __('Regenerate description', 'growtype-ai') ?></a>
                    <a href="#" class="button button-secondary button-compress" data-id="<?= $image['id'] ?>" style="<?= $is_compressed ? 'background: green;color: white;' : '' ?>"><?= $is_compressed ? __('Is compressed!', 'growtype-ai') : __('Compress photo', 'growtype-ai') ?></a>
                    <a href="#" class="button button-secondary button-featured" data-id="<?= $image['id'] ?>"><?= $is_featured ? __('Is featured!', 'growtype-ai') : __('Feature', 'growtype-ai') ?></a>
                    <a href="#" class="button button-secondary button-cover" data-id="<?= $image['id'] ?>"><?= $is_cover ? __('Is cover photo!', 'growtype-ai') : __('Cover photo', 'growtype-ai') ?></a>
                </div>
                <?php
                if ($real_esrgan) {
                    echo '<span style="color: green;display:inline-block;padding-top: 20px;">Upscaled</span>';
                }
                ?>

                <?php
                if ($compressed) {
                    echo '<span style="color: green;display:inline-block;padding-top: 20px;">Compressed</span>';
                }
                ?>
                <div style="padding: 5px;">
                    <p><b>Main Colors:</b> <?php echo $main_colors ?></p>
                    <p><b>Prompt:</b> <?php echo $prompt ?></p>
                    <p><b>Caption:</b> <?php echo $caption ?></p>
                    <p><b>Alt text:</b> <?php echo $alt_text ?></p>
                    <p><b>Tags:</b> <?php echo $tags ?></p>
                    <p><b>Model id:</b> <?php echo sprintf('<a href="?page=%s&action=%s&model=%s">' . growtype_ai_get_image_model_details($image['id'])['id'] . '</a>', Growtype_Ai_Admin::MODELS_PAGE_NAME, 'edit', growtype_ai_get_image_model_details($image['id'])['id']) ?></p>
                    <p><b>Image id:</b> <?php echo $image['id'] ?></p>
                    <?php if (isset($image['settings']['model_id'])) { ?>
                        <p><b>External model id:</b> <?php echo $image['settings']['model_id'] ?></p>
                    <?php } ?>
                    <?php if (isset($image['settings']['modelId'])) { ?>
                        <p><b>External model id:</b> <?php echo $image['settings']['modelId'] ?></p>
                    <?php } ?>
                    <p><b>NSFW:</b> <input type="checkbox" name="settings[nsfw]" <?php echo checked($image['settings']['nsfw'] ?? false) ?>/></p>
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
                        action: 'growtype_ai_admin_remove_image',
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

            $('input[type="checkbox"][name="settings[nsfw]"]').click(function (e) {
                let image = $(this).closest('.image');
                let isChecked = $(this).is(':checked');
                let name = $(this).attr('name');

                if (isChecked) {
                    image.addClass('is-nsfw')
                } else {
                    image.removeClass('is-nsfw')
                }

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'growtype_ai_admin_update_image',
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
            })

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
                        action: 'growtype_ai_admin_update_image',
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
                        action: 'growtype_ai_admin_update_image',
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
                        action: 'growtype_ai_admin_compress_image',
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


