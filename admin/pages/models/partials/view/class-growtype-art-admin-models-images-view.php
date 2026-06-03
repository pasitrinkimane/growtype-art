<?php

/**
 * Handles rendering of image groups on the model edit page:
 * - Inline <style> and <script> (output once via admin_head / admin_footer hooks)
 * - Stats modal per image group
 * - Relation-family grouped view (has_relations mode)
 * - Standard grid view
 */
class Growtype_Art_Admin_Models_Images_View
{
    public function __construct()
    {
        add_action('admin_head',   [$this, 'output_styles']);
        add_action('admin_footer', [$this, 'output_scripts']);
    }

    // ──────────────────────────────────────────────
    // Styles / Scripts
    // ──────────────────────────────────────────────

    public function output_styles(): void
    {
        if (($_GET['page'] ?? '') !== Growtype_Art_Admin::MODELS_PAGE_NAME) {
            return;
        }
        ?>
        <style>
            /* Stats modal */
            .stats-modal-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.55);
                z-index: 99999;
                align-items: center;
                justify-content: center;
            }
            .stats-modal-overlay.is-open { display: flex; }
            .stats-modal-box {
                background: #fff;
                border-radius: 6px;
                padding: 28px 32px;
                min-width: 260px;
                box-shadow: 0 8px 32px rgba(0,0,0,.25);
                position: relative;
            }
            .stats-modal-box h3 { margin-top: 0; margin-bottom: 16px; font-size: 16px; }
            .stats-modal-close {
                position: absolute;
                top: 10px; right: 14px;
                background: none; border: none;
                font-size: 20px; cursor: pointer;
                color: #666; line-height: 1;
            }
            .stats-modal-close:hover { color: #000; }

            /* Relation family groups */
            .relation-family {
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                margin-bottom: 20px;
                overflow: hidden;
            }
            .relation-family-header {
                background: #f0f0f1;
                padding: 6px 12px;
                font-size: 12px;
                color: #50575e;
                border-bottom: 1px solid #c3c4c7;
            }
            
            .relation-family-parent { border-bottom: 2px solid #72e69a; }

            /* List-view toggle */
            .b-imgs.is-list-view { grid-template-columns: 1fr !important; }
            .b-imgs.is-list-view .image {
                display: grid;
                grid-template-columns: 1fr 3fr;
                gap: 20px;
            }
            .b-imgs.is-list-view .image-preview {
                max-width: 300px;
                border-right: 1px solid #dcdcde;
                border-bottom: none;
                height: 100%;
            }
            .b-imgs.is-list-view .image-details-metrics { grid-template-columns: 1fr 1fr; }
        </style>
        <?php
    }

    public function output_scripts(): void
    {
        if (($_GET['page'] ?? '') !== Growtype_Art_Admin::MODELS_PAGE_NAME) {
            return;
        }
        ?>
        <script>
        (function () {
            document.addEventListener('click', function (e) {
                // Stats modal open
                if (e.target.matches('.stats-btn')) {
                    document.getElementById(e.target.dataset.modal).classList.add('is-open');
                }
                // Stats modal close
                if (e.target.matches('.stats-modal-close') || e.target.matches('.stats-modal-overlay')) {
                    e.target.closest('.stats-modal-overlay').classList.remove('is-open');
                }
                // Toggle grid ↔ list
                if (e.target.matches('.toggle-view-btn')) {
                    var grid = document.getElementById(e.target.dataset.target);
                    if (grid) {
                        grid.classList.toggle('is-list-view');
                        e.target.textContent = grid.classList.contains('is-list-view')
                            ? 'Toggle to Grid' : 'Toggle to List';
                    }
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.stats-modal-overlay.is-open')
                        .forEach(function (el) { el.classList.remove('is-open'); });
                }
            });
        }());
        </script>
        <?php
    }

    // ──────────────────────────────────────────────
    // Public render entry-point
    // ──────────────────────────────────────────────

    /**
     * Render all image groups for a model.
     *
     * @param array $grouped_by_basename  Output of self::build_grouped_by_basename()
     * @param int   $model_id
     * @param int   $offset
     * @param int   $limit
     * @param bool  $has_relations        Whether to use the relation-family layout
     */
    public static function render_groups(
        array $grouped_by_basename,
        int   $model_id,
        int   $offset,
        int   $limit,
        bool  $has_relations
    ): void {
        if (empty($grouped_by_basename)) {
            echo '<p>No images found matching this filter.</p>';
            return;
        }

        foreach ($grouped_by_basename as $group_key => $images_group) {
            if (empty($images_group)) {
                continue;
            }

            $stats    = growtype_art_get_model_images_group_stats($model_id, $group_key);
            $modal_id = 'stats-modal-' . esc_attr($group_key);
            ?>
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                    <div class="title">
                        <h3 style="margin: 0;"><?= ucfirst($group_key) ?> images</h3>
                    </div>
                    <div class="actions">
                        <?php if (!$has_relations): ?>
                            <button class="button button-secondary toggle-view-btn"
                                    data-target="b-imgs-<?= esc_attr($group_key) ?>">Toggle to List</button>
                        <?php endif; ?>
                        <button class="button button-secondary stats-btn" data-modal="<?= $modal_id ?>">Stats</button>
                    </div>
                </div>

                <?= self::render_stats_modal($modal_id, $group_key, $stats) ?>

                <?php
                $images_group_sliced = array_slice($images_group, $offset, $limit, true);

                if ($has_relations) {
                    self::render_relation_families($images_group_sliced);
                } else {
                    self::render_grid($images_group_sliced, $group_key);
                }
                ?>
            </div>
            <?php
        }
    }

    // ──────────────────────────────────────────────
    // Private rendering helpers
    // ──────────────────────────────────────────────

    private static function render_stats_modal(string $modal_id, string $group_key, array $stats): string
    {
        ob_start();
        ?>
        <div id="<?= $modal_id ?>" class="stats-modal-overlay">
            <div class="stats-modal-box">
                <button class="stats-modal-close" title="Close">&times;</button>
                <h3><?= ucfirst($group_key) ?> images stats</h3>
                <p style="margin:0">Total images: <b><?= $stats['total'] ?></b></p>
                <p style="margin:0">Nsfw images: <b><?= $stats['nsfw'] ?></b></p>
                <p style="margin:0">Naked images: <b><?= $stats['naked'] ?></b></p>
                <p style="margin:0">Featured images: <b><?= $stats['featured'] ?></b></p>
                <p style="margin:0">Cover images: <b><?= $stats['cover'] ?></b></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_grid(array $images_group_sliced, string $group_key): void
    {
        echo '<div id="b-imgs-' . esc_attr($group_key) . '" class="b-imgs" style="gap: 10px;display: grid;">';
        foreach ($images_group_sliced as $same_images_group) {
            foreach ($same_images_group as $image) {
                echo Growtype_Art_Admin_Images::preview_image_from_data($image);
            }
        }
        echo '</div>';
    }

    private static function render_relation_families(array $images_group_sliced): void
    {
        // Index root images (no parent) by their own ID
        $roots_by_id = [];
        foreach (($images_group_sliced[''] ?? []) as $img) {
            $roots_by_id[(int)$img['id']] = $img;
        }

        $rendered_parent_ids = [];

        ?>
        <style>
            .relation-family-images {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
                margin-top: 10px;
            }
            @media (max-width: 1300px) {
                .relation-family-images {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
            @media (max-width: 900px) {
                .relation-family-images {
                    grid-template-columns: repeat(1, minmax(0, 1fr));
                }
            }
        </style>
        <?php

        // Render each family: parent + children
        foreach ($images_group_sliced as $group_id => $sibling_imgs) {
            if ($group_id === '') {
                continue; // roots are rendered as part of their family
            }

            $parent_id = (int)$group_id;

            echo '<div class="relation-family">';

            if (isset($roots_by_id[$parent_id])) {
                echo '<div class="relation-family-header">Parent id: ' . $parent_id . '</div>';
                $rendered_parent_ids[$parent_id] = true;
            } else {
                echo '<div class="relation-family-header">Parent id: ' . $parent_id . ' (outside current page)</div>';
            }

            echo '<div class="relation-family-images">';

            if (isset($roots_by_id[$parent_id])) {
                echo '<div class="relation-family-parent">';
                echo Growtype_Art_Admin_Images::preview_image_from_data($roots_by_id[$parent_id]);
                echo '</div>';
            }

            foreach ($sibling_imgs as $child_img) {
                echo '<div class="relation-family-child">';
                echo Growtype_Art_Admin_Images::preview_image_from_data($child_img);
                echo '</div>';
            }

            echo '</div>'; // .relation-family-images

            echo '</div>'; // .relation-family
        }

        // Standalone parents whose children aren't on this page
        foreach ($roots_by_id as $img_id => $img) {
            if (!isset($rendered_parent_ids[$img_id])) {
                echo '<div class="relation-family">';
                echo '<div class="relation-family-header">Parent id: ' . $img_id . ' (no children on this page)</div>';
                
                echo '<div class="relation-family-images">';
                echo '<div class="relation-family-parent">';
                echo Growtype_Art_Admin_Images::preview_image_from_data($img);
                echo '</div>';
                echo '</div>'; // .relation-family-images

                echo '</div>';
            }
        }
    }

    // ──────────────────────────────────────────────
    // Data helper
    // ──────────────────────────────────────────────

    /**
     * Regroup flat image lists by parent_image_id (or own id for roots).
     *
     * @param  array $grouped_images  Result of growtype_art_get_model_images_grouped()
     * @return array  ['original' => [group_id => [image, ...]], ...]
     */
    public static function build_grouped_by_basename(array $grouped_images): array
    {
        $grouped_by_basename = [];

        foreach ($grouped_images as $group_key => $images) {
            foreach ($images as $image) {
                $group_id = !empty($image['settings']['parent_image_id'])
                    ? $image['settings']['parent_image_id']
                    : '';

                $grouped_by_basename[$group_key][$group_id][] = $image;
            }
        }

        return $grouped_by_basename;
    }
}
