<?php

class Growtype_Ai_Admin_Images
{

    public function __construct()
    {
        add_action('admin_menu', array ($this, 'items_tab_init'));
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

        $query_args = [
            [
                'limit' => 200,
                'offset' => $offset
            ]
        ];

        if ($random) {
            $query_args[0]['orderby'] = 'rand()';
        }

        $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE, $query_args);

        echo '<a href="https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai&offset=500&random=1" style="padding-top: 20px;padding-bottom: 10px;display: inline-block;">https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai&offset=500&random=1</a>';

        echo '<div class="b-imgs wrap" style="gap: 10px;display: grid;">';

        foreach ($images as $image) {
            $image = growtype_ai_get_image_details($image['id']);
            echo $this->preview_image($image);
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

    public static function preview_image($image)
    {
        $prompt = isset($image['settings']['prompt']) ? $image['settings']['prompt'] : '';
        $caption = isset($image['settings']['caption']) ? $image['settings']['caption'] : '';
        $alt_text = isset($image['settings']['alt_text']) ? $image['settings']['alt_text'] : '';
        $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : '';
        $tags = isset($image['settings']['tags']) ? json_decode($image['settings']['tags'], true) : '';
        $tags = !empty($tags) ? implode(', ', $tags) : '';

        $compressed = isset($image['settings']['compressed']) ? true : false;
        $real_esrgan = isset($image['settings']['real_esrgan']) ? true : false;

        $img_url = growtype_ai_get_image_url($image['id']);

        $main_colors = isset($image['settings']['main_colors']) && !empty($image['settings']['main_colors']) ? implode(',', json_decode($image['settings']['main_colors'], true)) : '';

        $is_featured = isset($image['settings']['is_featured']) ? $image['settings']['is_featured'] : false;

        ob_start();

        if (!empty($img_url)) { ?>
            <div class="image <?= $is_featured ? 'is-featured' : '' ?>" data-id="<?= $image['id'] ?>">
                <a href="<?php echo $img_url ?>?v=<?php echo time() ?>" target="_blank" style="min-height: 100px;width: 100%;display: flex;margin-bottom: 10px;">
                    <img src="<?php echo $img_url ?>" alt="" style="max-width: 100%;">
                </a>
                <div style="display:flex;flex-wrap: wrap;gap: 15px;flex-direction: column;">
                    <a href="#" class="button button-primary button-delete" data-id="<?= $image['id'] ?>"><?= __('Delete', 'growtype-ai') ?></a>
                    <a href="<?= sprintf('/wp/wp-admin/admin.php?page=growtype-ai-models&action=generate-image-content&model=%s&image=%s', growtype_ai_get_image_model_details($image['id'])['id'], $image['id']) ?>" class="button button-secondary button-regenerate" data-id="<?= $image['id'] ?>"><?= __('Regenerate description', 'growtype-ai') ?></a>
                    <a href="#" class="button button-secondary button-featured" data-id="<?= $image['id'] ?>"><?= $is_featured ? __('Is featured!', 'growtype-ai') : __('Feature', 'growtype-ai') ?></a>
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

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'remove_image',
                        image_id: $(this).attr('data-id'),
                    },
                    success: function (res) {
                        image.remove();
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            })


            $('.button-featured').click(function (e) {
                e.preventDefault();

                let image = $(this).closest('.image');
                let btn = $(this);

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'featured_image',
                        image_id: $(this).attr('data-id'),
                    },
                    success: function (res) {
                        if (res.is_featured) {
                            image.addClass('is-featured')
                            btn.text('Is featured!')
                        } else {
                            image.removeClass('is-featured')
                            btn.text('Feature')
                        }
                    },
                    error: function (err) {
                        console.error(err);
                    }
                })
            })
        </script>
        <?php
    }
}


