<?php

defined('ABSPATH') || exit;

/**
 * Renders the content generator form HTML.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Form::render([...])
 */
class Growtype_Art_Admin_Content_Generator_Form
{
    /**
     * @param array $data {
     *   first_type              – active content type tab (image|text|video|audio)
     *   reuse_character_label   – pre-filled character search text
     *   reuse_character_id      – pre-filled character ID
     *   reuse_image             – pre-filled reference image URL
     *   providers_by_type       – full providers map ['image'=>..., 'text'=>..., ...]
     *   first_prov_key          – initially selected provider key
     *   default_models          – models for the initial provider
     * }
     */
    public static function render(array $data): void
    {
        extract($data, EXTR_SKIP);

        ?>
        <div class="wrap" id="gc-wrap">

            <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                ✨ <?php esc_html_e('Generator', 'growtype-art'); ?>
            </h1>
            <p style="color:#64748b;margin-top:0;">
                <?php esc_html_e('Select content type, provider and model, enter your prompt, and generate AI-powered content.', 'growtype-art'); ?>
            </p>

            <!-- ── Content type toggle ─────────────────────────────────────── -->
            <div class="gc-type-toggle" id="gc-type-toggle">
                <button type="button" class="gc-type-btn<?php echo $first_type === 'image' ? ' active' : ''; ?>" data-type="image">🖼️ <?php esc_html_e('Image', 'growtype-art'); ?></button>
                <button type="button" class="gc-type-btn<?php echo $first_type === 'text'  ? ' active' : ''; ?>" data-type="text">✍️  <?php esc_html_e('Text',  'growtype-art'); ?></button>
                <button type="button" class="gc-type-btn<?php echo $first_type === 'video' ? ' active' : ''; ?>" data-type="video">🎬 <?php esc_html_e('Video', 'growtype-art'); ?></button>
                <button type="button" class="gc-type-btn<?php echo $first_type === 'audio' ? ' active' : ''; ?>" data-type="audio">🎵 <?php esc_html_e('Audio', 'growtype-art'); ?></button>
            </div>

            <!-- ── Generator form ──────────────────────────────────────────── -->
            <div class="gc-card">

                <!-- Character autocomplete -->
                <div class="gc-field" style="margin-bottom:16px;">
                    <label class="gc-label" for="gc-character-search">
                        <?php esc_html_e('Character', 'growtype-art'); ?>
                    </label>
                    <div class="gc-ac-wrap" id="gc-character-wrap">
                        <input type="text"
                               id="gc-character-search"
                               class="gc-input gc-ac-input"
                               placeholder="<?php esc_attr_e('Search characters…', 'growtype-art'); ?>"
                               autocomplete="off"
                               value="<?php echo esc_attr($reuse_character_label); ?>">
                        <input type="hidden" id="gc-character" value="<?php echo (int)$reuse_character_id; ?>">
                        <div id="gc-character-dropdown" class="gc-ac-dropdown" style="display:none;"></div>
                    </div>
                </div>

                <!-- Reference image (optional) -->
                <div class="gc-field gc-ref-image-field" id="gc-ref-image-wrap" style="margin-bottom:16px;">
                    <label class="gc-label">
                        <?php esc_html_e('Reference Image', 'growtype-art'); ?>
                        <span class="gc-label-hint"><?php esc_html_e('optional', 'growtype-art'); ?></span>
                    </label>
                    <div class="gc-ref-input-row">
                        <input type="text"
                               id="gc-reference-image-url"
                               class="gc-input"
                               placeholder="<?php esc_attr_e('Paste image URL or click Browse…', 'growtype-art'); ?>"
                               value="<?php echo esc_attr($reuse_image); ?>"
                               autocomplete="off">
                        <button type="button" id="gc-ref-browse" class="gc-btn-secondary gc-ref-browse-btn">
                            📁 <?php esc_html_e('Browse', 'growtype-art'); ?>
                        </button>
                    </div>
                    <div class="gc-ref-preview" id="gc-ref-preview"<?php if (!$reuse_image) echo ' style="display:none;"'; ?>>
                        <img id="gc-ref-thumb"
                             src="<?php echo esc_url($reuse_image); ?>"
                             alt="reference"
                             class="gc-ref-thumb"
                             onerror="this.style.opacity='.3'">
                        <div class="gc-ref-meta">
                            <span class="gc-ref-label" id="gc-ref-label"><?php echo esc_html($reuse_image ? basename(parse_url($reuse_image, PHP_URL_PATH)) : ''); ?></span>
                            <button type="button" id="gc-ref-remove" class="gc-ref-remove">✕ <?php esc_html_e('Remove', 'growtype-art'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="gc-form-row">

                    <!-- Provider select -->
                    <div class="gc-field">
                        <label class="gc-label" for="gc-provider">
                            <?php esc_html_e('Provider', 'growtype-art'); ?>
                        </label>
                        <select id="gc-provider" class="gc-select">
                            <?php foreach ($providers_by_type[$first_type] as $key => $prov_data) : ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                    <?php selected($key, $first_prov_key); ?>>
                                    <?php echo esc_html($prov_data['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Model select -->
                    <div class="gc-field">
                        <label class="gc-label" for="gc-model">
                            <?php esc_html_e('Model', 'growtype-art'); ?>
                        </label>
                        <select id="gc-model" class="gc-select">
                            <?php foreach ($default_models as $key => $meta) : 
                                $label = is_array($meta) ? ($meta['label'] ?? $key) : $meta;
                                $ref   = is_array($meta) ? ($meta['ref'] ?? false) : false;
                            ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?><?php echo $ref ? ' 🖼️' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <!-- No-providers notice (hidden by default) -->
                <div id="gc-no-providers" style="display:none;margin-top:14px;padding:12px 16px;background:#fef9c3;border:1px solid #fde047;border-radius:8px;font-size:13px;color:#713f12;">
                    <?php esc_html_e('No providers available for this content type yet.', 'growtype-art'); ?>
                </div>

                <!-- Prompt textarea -->
                <div class="gc-field" style="margin-top:16px;">
                    <label class="gc-label" for="gc-prompt">
                        <?php esc_html_e('Prompt', 'growtype-art'); ?>
                    </label>
                    <textarea id="gc-prompt" class="gc-textarea"
                              placeholder="<?php esc_attr_e('Enter your prompt here…', 'growtype-art'); ?>"
                              rows="6"></textarea>
                </div>

                <!-- Post-processing options -->
                <div class="gc-post-options" style="margin-top:12px;display:flex;gap:20px;flex-wrap:wrap;">
                    <label class="gc-checkbox-label">
                        <input type="checkbox" id="gc-opt-compress" checked>
                        <span><?php esc_html_e('Compress image', 'growtype-art'); ?></span>
                    </label>
                    <label class="gc-checkbox-label">
                        <input type="checkbox" id="gc-opt-bg-remove">
                        <span><?php esc_html_e('Remove background', 'growtype-art'); ?></span>
                    </label>
                </div>

                <!-- Actions -->
                <div class="gc-actions">
                    <button id="gc-generate-btn" type="button" class="gc-btn-primary">
                        <span class="gc-btn-icon">⚡</span>
                        <span id="gc-btn-label"><?php esc_html_e('Generate', 'growtype-art'); ?></span>
                    </button>
                    <button id="gc-clear-btn" type="button" class="gc-btn-secondary">
                        <?php esc_html_e('Clear', 'growtype-art'); ?>
                    </button>
                </div>

            </div><!-- .gc-card -->

            <!-- ── Loading overlay ────────────────────────────────────────── -->
            <div id="gc-loading" style="display:none;" class="gc-loading-overlay">
                <div class="gc-spinner"></div>
                <p><?php esc_html_e('Generating…', 'growtype-art'); ?></p>
            </div>

            <!-- ── Recent Generations ──────────────────────────────────────── -->
            <div id="gc-recent-wrap" class="gc-card gc-recent-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h2 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;">
                        🕐 <?php esc_html_e('Recent Generations', 'growtype-art'); ?>
                    </h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . Growtype_Art_Admin_History::PAGE_NAME)); ?>"
                       style="font-size:12px;color:#6366f1;text-decoration:none;font-weight:600;">
                        <?php esc_html_e('View all →', 'growtype-art'); ?>
                    </a>
                </div>
                <div id="gc-recent-table">
                    <?php echo Growtype_Art_Admin_History::render_recent(5); ?>
                </div>
            </div>

            <!-- Shared gh-* table styles & modal/copy-btn JS -->
            <?php Growtype_Art_Admin_History::render_table_styles(); ?>
            <?php Growtype_Art_Admin_History::render_table_scripts(); ?>

            <!-- Recent card overflow wrapper -->
            <style>.gc-recent-card { overflow-x: auto; }</style>

        </div><!-- #gc-wrap -->
        <?php
    }
}
