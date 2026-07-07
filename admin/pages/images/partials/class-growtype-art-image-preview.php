<?php

class Growtype_Art_Image_Preview
{
    const IMAGE_VERSION = '1.1.9.1';

    public function __construct()
    {
        add_action('admin_head', [$this, 'output_styles']);
        add_action('admin_footer', [$this, 'output_scripts']);
        add_action('wp_ajax_growtype_art_admin_set_parent_image', [$this, 'set_parent_image_callback']);
    }

    /** @var array|null Lazy-loaded map of parent_id => [child_id, ...] */
    private static $children_map = null;

    /**
     * Load all parent→children relationships in one query and cache them.
     */
    private static function get_children_map(): array
    {
        if (self::$children_map === null) {
            global $wpdb;
            $rows = $wpdb->get_results(
                "SELECT image_id, meta_value AS parent_id
                 FROM {$wpdb->prefix}growtype_art_image_settings
                 WHERE meta_key = 'parent_image_id'"
            );
            self::$children_map = [];
            foreach ($rows as $row) {
                self::$children_map[(int)$row->parent_id][] = (int)$row->image_id;
            }
        }
        return self::$children_map;
    }

    public static function render($image, $image_id = null)
    {
        if (empty($image) || !is_array($image)) {
            $image_id = $image_id !== null ? (int)$image_id : null;
            ob_start();
            ?>
            <div class="image" <?= $image_id !== null ? 'data-id="' . $image_id . '"' : '' ?>>
                Image doesnt exist
                <div class="image-missing-actions">
                    <?php if ($image_id !== null) { ?>
                        <a href="#" class="button button-primary growtype-ajax-button"
                           data-id="<?= $image_id ?>"
                           data-action="growtype_art_admin_remove_image"
                           data-success-action="remove"
                           data-confirm="<?= __('Are you sure you want to delete this image?', 'growtype-art') ?>"><?= __('Delete', 'growtype-art') ?></a>
                    <?php } ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        $prompt      = $image['settings']['prompt'] ?? '';
        $caption     = $image['settings']['caption'] ?? '';
        $alt_text    = $image['settings']['alt_text'] ?? '';
        $tags        = isset($image['settings']['tags']) ? implode(', ', json_decode($image['settings']['tags'], true) ?: []) : '';
        $real_esrgan = !empty($image['settings']['real_esrgan']);
        $img_url     = growtype_art_build_public_image_url($image);
        $provider    = !empty($image['settings']['provider']) ? $image['settings']['provider'] : '-';
        $has_model   = !empty($image['model_id']);
        $main_colors = isset($image['settings']['main_colors']) && !empty(json_decode($image['settings']['main_colors'], true))
            ? implode(',', json_decode($image['settings']['main_colors'], true)) : '';

        $is_featured    = !empty($image['settings']['is_featured']);
        $is_cover       = !empty($image['settings']['is_cover']);
        $is_intro_asset = !empty($image['settings']['is_intro_asset']);
        $is_compressed  = !empty($image['settings']['compressed']);

        $classes    = ['image'];
        $toggle_map = [
            'is_featured'    => 'is-featured',
            'is_cover'       => 'is-cover',
            'is_intro_asset' => 'is-intro-asset',
            'nsfw'           => 'is-nsfw',
            'nudity'         => 'is-nudity',
            'porn'           => 'is-porn',
            'private'        => 'is-private',
            'compressed'     => 'is-compressed',
        ];

        foreach ($toggle_map as $key => $class) {
            if (!empty($image['settings'][$key])) {
                $classes[] = $class;
            }
        }

        ob_start();
        ?>
        <div class="<?= implode(' ', $classes) ?>" data-id="<?= $image['id'] ?>">
            <?php if (!empty($img_url)) { ?>
                <div class="image-preview">
                    <a href="<?php echo growtype_art_image_get_alternative_format($img_url) ?>?v=<?php echo self::IMAGE_VERSION ?>" target="_blank" class="image-preview-link">
                        <?php if (in_array($image['extension'], ['jpg', 'jpeg', 'png', 'webp', 'gif'])) { ?>
                            <img src="<?php echo growtype_art_image_get_alternative_format($img_url) ?>?v=<?php echo self::IMAGE_VERSION ?>" alt="" class="image-preview-img" loading="lazy">
                        <?php } elseif (in_array($image['extension'], ['mp3', 'wav', 'ogg', 'm4a'])) { ?>
                            <audio controls preload="none" class="image-preview-audio">
                                <source src="<?php echo $img_url ?>" type="audio/<?= $image['extension'] ?>">
                            </audio>
                        <?php } else { ?>
                            <video width="100%" height="100%" controls loop muted preload="none" class="lazy-video">
                                <source type="video/mp4" data-src="<?php echo $img_url ?>">
                            </video>
                        <?php } ?>
                    </a>
                </div>

                <div class="image-details">
                    <div class="image-actions">
                        <a href="#" class="button button-primary growtype-ajax-button"
                           data-action="growtype_art_admin_remove_image"
                           data-success-action="remove"
                           data-confirm="<?= __('Are you sure?', 'growtype-art') ?>"><?= __('Delete', 'growtype-art') ?></a>

                        <?php if ($has_model) { ?>
                            <a style="display: none" href="<?= sprintf('/wp/wp-admin/admin.php?page=growtype-art-models&action=generate-image-content&model=%s&image=%s', $image['model_id'], $image['id']) ?>" class="button button-secondary button-regenerate"><?= __('Regenerate description', 'growtype-art') ?></a>
                        <?php } ?>

                        <a href="#" class="button button-secondary growtype-ajax-button <?= $is_compressed ? 'is-compressed-btn' : '' ?>"
                           data-action="growtype_art_admin_compress_image"
                           data-success-action="addClass:is-compressed"
                           data-success-text="<?= __('Is compressed!', 'growtype-art') ?>"><?= $is_compressed ? __('Is compressed!', 'growtype-art') : __('Compress photo', 'growtype-art') ?></a>

                        <?php if ($has_model) { ?>
                            <a style="display: none" href="<?= sprintf('/wp/wp-admin/admin.php?page=growtype-art-models&action=generate-images&model=%s&image=%s', $image['model_id'], $image['id']) ?>" target="_blank" class="button button-secondary button-generate"><?= __('Generate new image', 'growtype-art') ?></a>
                        <?php } ?>

                        <a href="#" class="button button-secondary growtype-ajax-button <?= $is_featured ? 'is-active' : '' ?>"
                           data-action="growtype_art_admin_update_image"
                           data-success-action="toggle"
                           data-name="settings[is_featured]"
                           data-class="is-featured"
                           data-on="<?= __('Is featured!', 'growtype-art') ?>"
                           data-off="<?= __('Feature', 'growtype-art') ?>"><?= $is_featured ? __('Is featured!', 'growtype-art') : __('Feature', 'growtype-art') ?></a>

                        <a href="#" class="button button-secondary growtype-ajax-button <?= $is_cover ? 'is-active' : '' ?>"
                           data-action="growtype_art_admin_update_image"
                           data-success-action="toggle"
                           data-name="settings[is_cover]"
                           data-class="is-cover"
                           data-on="<?= __('Is cover photo!', 'growtype-art') ?>"
                           data-off="<?= __('Cover photo', 'growtype-art') ?>"><?= $is_cover ? __('Is cover photo!', 'growtype-art') : __('Cover photo', 'growtype-art') ?></a>

                        <a href="#" class="button button-secondary growtype-ajax-button <?= $is_intro_asset ? 'is-active' : '' ?>"
                           data-action="growtype_art_admin_update_image"
                           data-success-action="toggle"
                           data-name="settings[is_intro_asset]"
                           data-class="is-intro-asset"
                           data-on="<?= __('Is intro asset!', 'growtype-art') ?>"
                           data-off="<?= __('Intro asset', 'growtype-art') ?>"><?= $is_intro_asset ? __('Is intro asset!', 'growtype-art') : __('Intro asset', 'growtype-art') ?></a>
                    </div>

                    <?php if ($real_esrgan) { ?>
                        <span class="image-upscaled-badge">Upscaled</span>
                    <?php } ?>

                    <div class="image-details-metrics">
                        <div class="image-meta-primary">
                            <p><b>Name:</b> <?php echo $image['name'] ?? '-' ?></p>
                            <p><b>Provider:</b> <?php echo $provider ?></p>
                            <p class="image-main-colors"><b>Main Colors:</b> <?php echo $main_colors ?></p>
                            <p><b>Prompt:</b> <?php echo $prompt ?></p>
                            <p><b>Caption:</b> <?php echo $caption ?></p>
                            <p><b>Alt text:</b> <?php echo $alt_text ?></p>
                        </div>

                        <?php if ($has_model) { ?>
                            <div class="image-meta-tags">
                                <p><b>Tags:</b> <?php echo $tags ?></p>
                            </div>
                        <?php } ?>

                        <div class="image-meta-ids">
                            <?php if ($has_model) { ?>
                                <p><b>Model id:</b> <?php echo sprintf('<a href="?page=%s&action=%s&model=%s">' . $image['model_id'] . '</a>', Growtype_Art_Admin::MODELS_PAGE_NAME, 'edit', $image['model_id']) ?></p>
                            <?php } else { ?>
                                <p><b>Model:</b> No model assigned</p>
                            <?php } ?>
                            <p><b>File id:</b> <?php echo $image['id'] ?></p>
                            <?php if (isset($image['settings']['id'])) { ?>
                                <p><b>External image id:</b> <?php echo $image['settings']['id'] ?></p>
                            <?php } ?>
                            <?php if (isset($image['settings']['model_id'])) { ?>
                                <p><b>External model id:</b> <?php echo $image['settings']['model_id'] ?></p>
                            <?php } ?>
                            <?php if (isset($image['settings']['modelId'])) { ?>
                                <p><b>External model id:</b> <?php echo $image['settings']['modelId'] ?></p>
                            <?php } ?>
                        </div>

                        <?php if ($has_model) { ?>
                            <div class="image-meta-flags">
                                <p><b>EROTIC(NSFW):</b> <input type="checkbox" name="settings[nsfw]" <?php echo checked($image['settings']['nsfw'] ?? false) ?>/></p>
                                <p><b>NUDITY:</b> <input type="checkbox" name="settings[nudity]" <?php echo checked($image['settings']['nudity'] ?? false) ?>/></p>
                                <p><b>PORN:</b> <input type="checkbox" name="settings[porn]" <?php echo checked($image['settings']['porn'] ?? false) ?>/></p>
                                <hr>
                                <p><b>PRIVATE:</b> <input type="checkbox" name="settings[private]" <?php echo checked($image['settings']['private'] ?? false) ?>/></p>
                            </div>
                        <?php } ?>

                        <?php if ($has_model) { ?>
                            <div class="image-meta-relations">
                                <p><b>Relation:</b></p>
                                <div class="relation-set-parent" style="margin-bottom: 8px;">
                                    <p style="margin-bottom: 4px;">
                                        <b class="relation-label">Parent file:</b>
                                        <?php if (!empty($image['settings']['parent_image_id'])): ?>
                                            <a href="?page=<?php echo Growtype_Art_Admin::MODELS_PAGE_NAME ?>&action=edit&model=<?php echo $image['model_id'] ?>&parent_image_id=<?php echo $image['settings']['parent_image_id'] ?>"><?php echo $image['settings']['parent_image_id'] ?></a>
                                            <a href="#" class="relation-remove-parent" data-child-id="<?= (int)$image['id'] ?>" title="Remove parent">✕</a>
                                        <?php else: ?>
                                            <span>None</span>
                                        <?php endif; ?>
                                    </p>
                                    <input type="number" class="relation-parent-input" placeholder="Parent image ID" min="1">
                                    <button class="button button-small relation-set-parent-btn" data-child-id="<?= (int)$image['id'] ?>">Set parent</button>
                                    <span class="relation-parent-status"></span>
                                </div>
                                <?php
                                $child_ids = self::get_children_map()[(int)$image['id']] ?? [];
                                if (!empty($child_ids)): ?>
                                    <p>
                                        <b class="relation-label">Child files:</b>
                                        <?php foreach ($child_ids as $i => $child_id): ?>
                                            <span class="relation-child-item" data-child-id="<?= (int)$child_id ?>">
                                                <a href="?page=<?php echo Growtype_Art_Admin::MODELS_PAGE_NAME ?>&action=edit&model=<?php echo (int)$image['model_id'] ?>&parent_image_id=<?php echo (int)$image['id'] ?>"><?= (int)$child_id ?></a>
                                                <a href="#" class="relation-remove-child" data-child-id="<?= (int)$child_id ?>" data-parent-id="<?= (int)$image['id'] ?>" title="Unlink child">✕</a><?= $i < count($child_ids) - 1 ? ',' : '' ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                                <div class="relation-add-child">
                                    <input type="number" class="relation-child-input" placeholder="Child image ID" min="1">
                                    <button class="button button-small relation-add-child-btn" data-parent-id="<?= (int)$image['id'] ?>">+ Add child</button>
                                    <span class="relation-add-status"></span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php

        return ob_get_clean();
    }

    public function set_parent_image_callback()
    {
        $child_id    = isset($_POST['child_id'])   ? (int)$_POST['child_id']   : 0;
        $parent_id   = isset($_POST['parent_id'])  ? (int)$_POST['parent_id']  : 0;
        $action_type = $_POST['action_type'] ?? '';

        if (!$child_id) {
            return wp_send_json(['message' => 'Invalid child ID'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'growtype_art_image_settings';

        if ($action_type === 'remove') {
            $wpdb->delete($table, ['image_id' => $child_id, 'meta_key' => 'parent_image_id']);
        } elseif ($action_type === 'add' && $parent_id) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE image_id = %d AND meta_key = 'parent_image_id'",
                $child_id
            ));
            if ($exists) {
                $wpdb->update($table, ['meta_value' => $parent_id], ['image_id' => $child_id, 'meta_key' => 'parent_image_id']);
            } else {
                $wpdb->insert($table, ['image_id' => $child_id, 'meta_key' => 'parent_image_id', 'meta_value' => $parent_id]);
            }
        } else {
            return wp_send_json(['message' => 'Invalid action'], 400);
        }

        return wp_send_json(['message' => 'Success'], 200);
    }

    public function output_scripts()
    {
        $page = $_GET['page'] ?? '';
        if ($page !== Growtype_Art_Admin::MODELS_PAGE_NAME) {
            return;
        }
        ?>
        <script>
        (function ($) {
            // Remove parent from child
            $(document).on('click', '.relation-remove-parent', function (e) {
                e.preventDefault();
                if (!confirm('Remove parent link?')) return;
                var $btn = $(this);
                $.post(ajaxurl, {
                    action: 'growtype_art_admin_set_parent_image',
                    child_id: $btn.data('child-id'),
                    action_type: 'remove'
                }, function () {
                    $btn.closest('p').remove();
                });
            });

            // Remove a child from parent
            $(document).on('click', '.relation-remove-child', function (e) {
                e.preventDefault();
                if (!confirm('Unlink this child?')) return;
                var $btn = $(this);
                $.post(ajaxurl, {
                    action: 'growtype_art_admin_set_parent_image',
                    child_id: $btn.data('child-id'),
                    action_type: 'remove'
                }, function () {
                    $btn.closest('.relation-child-item').remove();
                });
            });

            // Set/Change parent
            $(document).on('click', '.relation-set-parent-btn', function (e) {
                e.preventDefault();
                var $btn    = $(this);
                var $wrap   = $btn.closest('.relation-set-parent');
                var $input  = $wrap.find('.relation-parent-input');
                var $status = $wrap.find('.relation-parent-status');
                var parentId = parseInt($input.val(), 10);
                var childId  = $btn.data('child-id');

                if (!parentId) { $status.text('Enter a parent image ID.').show(); return; }

                $btn.prop('disabled', true);
                $.post(ajaxurl, {
                    action:      'growtype_art_admin_set_parent_image',
                    child_id:    childId,
                    parent_id:   parentId,
                    action_type: 'add'
                }, function (res) {
                    $input.val('');
                    $status.text('Added! Reload to see.').show().delay(3000).fadeOut();
                    $btn.prop('disabled', false);
                }).fail(function () {
                    $status.text('Error.').show();
                    $btn.prop('disabled', false);
                });
            });

            // Add a child to parent
            $(document).on('click', '.relation-add-child-btn', function (e) {
                e.preventDefault();
                var $btn    = $(this);
                var $wrap   = $btn.closest('.relation-add-child');
                var $input  = $wrap.find('.relation-child-input');
                var $status = $wrap.find('.relation-add-status');
                var childId  = parseInt($input.val(), 10);
                var parentId = $btn.data('parent-id');

                if (!childId) { $status.text('Enter an image ID.').show(); return; }

                $btn.prop('disabled', true);
                $.post(ajaxurl, {
                    action:      'growtype_art_admin_set_parent_image',
                    child_id:    childId,
                    parent_id:   parentId,
                    action_type: 'add'
                }, function (res) {
                    $input.val('');
                    $status.text('Added! Reload to see.').show().delay(3000).fadeOut();
                    $btn.prop('disabled', false);
                }).fail(function () {
                    $status.text('Error.').show();
                    $btn.prop('disabled', false);
                });
            });
        }(jQuery));
        </script>
        <?php
    }

    public static function output_styles()
    {
        $page = $_GET['page'] ?? '';
        if ($page !== Growtype_Art_Admin::MODELS_PAGE_NAME && $page !== 'growtype-art') {
            return;
        }
        ?>
        <style>
            /* ── Premium Image Card Layout ── */
            .b-imgs .image {
                background: #ffffff;
                border: 1px solid #dcdcde;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                padding: 0 !important; /* override default .wrap .image padding */
            }

            .b-imgs .image:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            }

            .b-imgs .image.is-private {
                border-top: 5px solid #00afff;
                background: #f0f8ff !important;
            }

            .image-missing-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                flex-direction: column;
                padding: 20px;
            }

            .image-preview {
                width: 100%;
                background: #f6f7f7;
                border-bottom: 1px solid #dcdcde;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            .image-preview-link {
                display: flex;
                width: 100%;
                height: 100%;
                margin: 0;
            }

            .image-preview-img, .image-preview video {
                width: 100%;
                max-width: 100%;
                height: auto;
                object-fit: cover;
                display: block;
            }

            .image-preview-audio {
                width: 100%;
                margin: 20px;
            }

            .image-details {
                padding: 20px;
                display: flex;
                flex-direction: column;
                gap: 15px;
                flex-grow: 1;
            }

            .image-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding-bottom: 15px;
                border-bottom: 1px solid #f0f0f1;
            }
            
            .image-actions .button {
                border-radius: 6px;
                transition: all 0.2s ease;
            }

            .image-upscaled-badge {
                color: #00a32a;
                font-weight: 600;
                display: inline-block;
                padding: 4px 8px;
                background: #e6f6e9;
                border-radius: 4px;
                font-size: 12px;
                width: fit-content;
            }

            .image-main-colors {
                display: none;
            }

            /* compressed button active state */
            .button.is-compressed-btn {
                background: #00a32a !important;
                color: white !important;
                border-color: #008a20 !important;
            }

            /* ── Metrics Grid ── */
            .image-details-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                font-size: 13px;
                color: #3c434a;
            }

            .image-details-metrics > div {
                background: #f6f7f7;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #dcdcde;
            }

            .image-details-metrics p {
                margin: 0 0 8px 0;
                line-height: 1.5;
            }

            .image-details-metrics p:last-child {
                margin-bottom: 0;
            }

            .image-details-metrics b {
                color: #1d2327;
                font-weight: 600;
                display: inline-block;
                margin-right: 5px;
            }

            /* Flags specific styling */
            .image-meta-flags p {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .image-meta-flags input[type="checkbox"] {
                margin: 0;
                border-radius: 4px;
            }

            .image-meta-flags hr {
                border: 0;
                border-top: 1px solid #c3c4c7;
                margin: 12px 0;
            }

            /* relation labels (Parent file / Child files) */
            .relation-label {
                color: #008a20 !important;
            }

            /* relation remove buttons */
            .relation-remove-parent,
            .relation-remove-child {
                color: #d63638;
                text-decoration: none;
                font-size: 11px;
                margin-left: 4px;
                padding: 2px 4px;
                border-radius: 4px;
                background: #fcf0f1;
                vertical-align: middle;
                transition: background 0.2s, color 0.2s;
            }

            .relation-remove-parent:hover,
            .relation-remove-child:hover {
                color: #8a2424;
                background: #f6d8d9;
            }

            /* add child form */
            .relation-add-child {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 10px;
                flex-wrap: wrap;
                padding-top: 10px;
                border-top: 1px dashed #c3c4c7;
            }

            .relation-child-input {
                width: 100px;
                border-radius: 4px;
            }

            .relation-add-child-btn {
                border-radius: 4px;
            }

            .relation-add-status {
                font-size: 11px;
                color: #00a32a;
                font-weight: 500;
            }
        </style>
        <?php
    }
}
