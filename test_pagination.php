<?php
require_once('../../../../wp-load.php');

$total = growtype_art_get_model_total_images_amount(5825, [
    'parent_image_id' => '205274'
]);

var_dump($total);
