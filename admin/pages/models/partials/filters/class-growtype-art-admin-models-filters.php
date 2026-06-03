<?php

/**
 * Renders the filter bar (dropdowns + checkboxes + clear button)
 * used on the model edit/images page.
 */
class Growtype_Art_Admin_Models_Filters
{
    /**
     * @param array $params  Active filter values:
     *                       filter, file_type, hide_private, has_relations, parent_image_id
     */
    public static function render(array $params): void
    {
        $filter        = $params['filter']        ?? '';
        $file_type     = $params['file_type']     ?? '';
        $hide_private  = $params['hide_private']  ?? 0;
        $has_relations = $params['has_relations'] ?? 0;
        $parent_image_id = $params['parent_image_id'] ?? '';

        $is_active = !empty($filter) || !empty($file_type)
            || !empty($parent_image_id) || !empty($hide_private) || !empty($has_relations);
        ?>
        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">

            <div class="filter-select" style="margin-bottom: 20px;">
                <strong>Filter:</strong>
                <select onchange="window.location.href=this.value">
                    <option value="<?= remove_query_arg('filter') ?>" <?= empty($filter) ? 'selected' : '' ?>>All</option>
                    <option value="<?= add_query_arg('filter', 'erotic') ?>"       <?= $filter === 'erotic'       ? 'selected' : '' ?>>EROTIC</option>
                    <option value="<?= add_query_arg('filter', 'nudity') ?>"       <?= $filter === 'nudity'       ? 'selected' : '' ?>>Nudity</option>
                    <option value="<?= add_query_arg('filter', 'cover') ?>"        <?= $filter === 'cover'        ? 'selected' : '' ?>>Cover</option>
                    <option value="<?= add_query_arg('filter', 'featured') ?>"     <?= $filter === 'featured'     ? 'selected' : '' ?>>Featured</option>
                    <option value="<?= add_query_arg('filter', 'porn') ?>"         <?= $filter === 'porn'         ? 'selected' : '' ?>>Porn</option>
                    <option value="<?= add_query_arg('filter', 'private') ?>"      <?= $filter === 'private'      ? 'selected' : '' ?>>Private</option>
                    <option value="<?= add_query_arg('filter', 'is_intro_asset') ?>" <?= $filter === 'is_intro_asset' ? 'selected' : '' ?>>Intro Asset</option>
                </select>
            </div>

            <div class="filter-select" style="margin-bottom: 20px;">
                <strong>File type:</strong>
                <select onchange="window.location.href=this.value">
                    <option value="<?= remove_query_arg('file_type') ?>" <?= empty($file_type) ? 'selected' : '' ?>>All</option>
                    <option value="<?= add_query_arg('file_type', 'jpg') ?>"  <?= $file_type === 'jpg'  ? 'selected' : '' ?>>JPG</option>
                    <option value="<?= add_query_arg('file_type', 'webp') ?>" <?= $file_type === 'webp' ? 'selected' : '' ?>>WEBP</option>
                    <option value="<?= add_query_arg('file_type', 'mp4') ?>"  <?= $file_type === 'mp4'  ? 'selected' : '' ?>>MP4</option>
                </select>
            </div>

            <div class="filter-checkbox" style="margin-bottom: 20px; display: flex; align-items: center; gap: 5px;">
                <input type="checkbox" id="hide-private-checkbox"
                       <?= $hide_private ? 'checked' : '' ?>
                       onchange="window.location.href = this.checked
                           ? '<?= esc_url_raw(add_query_arg('hide_private', '1')) ?>'
                           : '<?= esc_url_raw(remove_query_arg('hide_private')) ?>'" />
                <label for="hide-private-checkbox"><strong>Hide private</strong></label>
            </div>

            <div class="filter-checkbox" style="margin-bottom: 20px; display: flex; align-items: center; gap: 5px;">
                <input type="checkbox" id="has-relations-checkbox"
                       <?= $has_relations ? 'checked' : '' ?>
                       onchange="window.location.href = this.checked
                           ? '<?= esc_url_raw(add_query_arg('has_relations', '1')) ?>'
                           : '<?= esc_url_raw(remove_query_arg('has_relations')) ?>'" />
                <label for="has-relations-checkbox"><strong>Has relations</strong></label>
            </div>

            <?php if ($is_active): ?>
                <div class="filter-select" style="margin-bottom: 20px; display: flex; align-items: center;">
                    <a href="<?= remove_query_arg(['filter', 'file_type', 'parent_image_id', 'hide_private', 'has_relations']) ?>"
                       class="button button-secondary">Clear filter</a>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
