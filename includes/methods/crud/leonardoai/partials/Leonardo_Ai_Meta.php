<?php

use partials\Leonardo_Ai_Base;

class Leonardo_Ai_Cpt extends Leonardo_Ai_Base
{

    public function __construct()
    {
        add_action('add_meta_boxes_meal', array ($this, 'meal_add_meta_box'));
        add_action('save_post_meal', array ($this, 'meal_save_meta_box_data'), 10, 2);
    }

    function meal_add_meta_box($post)
    {
        add_meta_box('meal_meta_box', 'Ai Images', array ($this, 'meal_images_meta_box'), 'meal', 'side', 'low');
    }

    function meal_images_meta_box($post)
    {
        $growtype_ai_images_ids = get_post_meta($post->ID, 'growtype_ai_images_ids', true);
        $growtype_ai_images_ids = !empty($growtype_ai_images_ids) ? json_decode($growtype_ai_images_ids, true) : [];

        ?>
        <div class='inside'>
            <h3>Generated Images</h3>
            <?php if (!empty($growtype_ai_images_ids)) {

                echo '<div class="b-imgs wrap" style="gap: 10px;display: grid;">';

                foreach ($growtype_ai_images_ids as $growtype_ai_images_id) {
                    echo Growtype_Ai_Admin_Images::preview_image($growtype_ai_images_id);
                }

                echo '</div>';

                echo Growtype_Ai_Admin_Images::image_delete_ajax();

            } else {
                echo 'No images generated yet';
            }
            ?>
        </div>
    <?php }

    function meal_save_meta_box_data($post_id)
    {
        if (!isset($_POST['food_meta_box_nonce']) || !wp_verify_nonce($_POST['food_meta_box_nonce'], basename(__FILE__))) {
            return;
        }

        if (isset($_REQUEST['book_author'])) {
            update_post_meta($post_id, '_book_author', sanitize_text_field($_POST['book_author']));
        }
    }
}


