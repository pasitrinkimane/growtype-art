<?php

defined('ABSPATH') || exit;

/**
 * Renders the content generator form HTML.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Form::render([...])
 */
class Growtype_Art_Admin_Content_Generator_Form
{
    private static function get_prompt_presets(): array
    {
        return apply_filters('growtype_art_content_generator_prompt_presets', [
            'default-original-image' => [
                'label'  => __('Default — Original High-Quality Image', 'growtype-art'),
                'prompt' => __('Create a materially original, high-quality image based on the requested concept. Use a clearly adult character when a person is present. Produce a distinct face, environment, wardrobe, lighting design, color palette, props, and surface details. Do not reproduce logos, watermarks, brand identifiers, copyrighted characters, exact typography, or signature design elements. Photorealistic, detailed skin and hair, realistic anatomy and hands, crisp focus, clean edges, and no pixelation or compression artifacts.', 'growtype-art'),
            ],
            'sfw-adult-image' => [
                'label'  => __('SFW — Fully Clothed Adult', 'growtype-art'),
                'prompt' => __('Generate a safe-for-work image featuring a clearly adult character aged 21 or older. The character must be fully clothed with no visible nipples, breasts, buttocks, genitals, transparent clothing, or sexual activity. Use an original adult identity, natural pose, tasteful wardrobe, believable environment, polished composition, realistic anatomy and hands, detailed skin and hair, and crisp high-resolution photographic quality.', 'growtype-art'),
            ],
            'nsfw-explicit-nude-adult' => [
                'label'  => __('NSFW — Explicit Nude Adult', 'growtype-art'),
                'prompt' => __('Generate an explicit NSFW image of one clearly consenting adult aged 21 or older. Full nudity may be visible, including adult breasts, nipples, buttocks, and genitals. Use a completely original adult identity and setting with realistic anatomy, natural body proportions, accurate hands, detailed skin and hair, cinematic lighting, crisp focus, and high-resolution photographic quality. No minors, youthful appearance, age ambiguity, coercion, violence, incest, bestiality, or illegal content.', 'growtype-art'),
            ],
            'nsfw-explicit-adult-porn' => [
                'label'  => __('NSFW — Explicit Adult Porn Scene', 'growtype-art'),
                'prompt' => __('Generate a fully explicit pornographic scene involving only clearly consenting adults aged 21 or older. Explicit adult nudity and consensual sexual activity may be visible. Give every participant an unmistakably mature adult appearance and an original identity. Use realistic anatomy and body contact, accurate hands and limbs, detailed skin, believable expressions, cinematic lighting, crisp focus, and high-resolution photographic quality. No minors, youthful appearance, age ambiguity, coercion, violence, incest, bestiality, intoxication, or illegal content.', 'growtype-art'),
            ],
            'nsfw-erotic-post-cover' => [
                'label'  => __('NSFW — Erotic Post Cover', 'growtype-art'),
                'prompt' => __('Create a premium vertical erotic post-cover image featuring a clearly adult character aged 21 or older. Make the pose, expression, styling, and lighting strongly sensual while keeping the composition polished and suitable for a promotional cover. Use an original adult identity, original environment, deliberate negative space for optional headline placement, cinematic lighting, realistic anatomy and hands, detailed skin and hair, crisp edges, and high-resolution editorial quality. Do not render text, logos, watermarks, brands, minors, youthful appearance, age ambiguity, coercion, or illegal content.', 'growtype-art'),
            ],
            'nsfw-nude-covered-parts' => [
                'label'  => __('NSFW — Nude Adult, Intimate Parts Covered', 'growtype-art'),
                'prompt' => __('Generate a sensual implied-nude image of a clearly adult character aged 21 or older. The character may otherwise appear naked, but nipples and genitals must remain fully covered by hands, arms, crossed legs, hair, fabric, foreground objects, or careful framing. No visible areola, nipple, vulva, penis, anus, or explicit sexual activity. Use an original adult identity and setting, elegant posing, realistic anatomy and hands, detailed skin and hair, cinematic lighting, crisp focus, and premium high-resolution photographic quality.', 'growtype-art'),
            ],
            'nsfw-topless-covered-by-hands' => [
                'label'  => __('NSFW — Topless Adult, Breasts Covered by Hands', 'growtype-art'),
                'prompt' => __('Generate a sensual topless image of a clearly adult character aged 21 or older. The upper body may be bare, but both nipples and the full areola area must be completely covered by the character’s hands, arms, hair, fabric, or pose. The lower body must remain clothed or carefully framed with no visible genitals. Use an original mature adult identity, tasteful sensual expression, believable pose, accurate fingers and hand placement, realistic anatomy, detailed skin and hair, cinematic lighting, and crisp high-resolution photographic quality.', 'growtype-art'),
            ],
            'sexy-lingerie-same-character' => [
                'label'  => __('Sexy Lingerie — Preserve Adult Character', 'growtype-art'),
                'prompt' => __('Use the reference image to preserve the identity and recognizable facial features of the same clearly adult character aged 21 or older. Dress the character in new, elegant, sexy lingerie while keeping nipples and genitals covered by opaque fabric. Place the character in a newly designed environment with a different background, props, lighting, and color palette while retaining broad character continuity. Sensual editorial pose, realistic anatomy and hands, detailed skin, hair, and fabric, cinematic lighting, crisp focus, and premium high-resolution photographic quality. No minors, youthful appearance, age ambiguity, explicit sexual activity, logos, or watermarks.', 'growtype-art'),
            ],
            'reference-reimagining' => [
                'label'  => __('Reference Reimagining — New Character & Background', 'growtype-art'),
                'prompt' => __('Use the reference image only as a composition guide. Create a materially original image with a completely new adult character: different face, hair, body features, wardrobe, and expression. Replace the entire background with a new environment, lighting design, color palette, furniture, props, and surface details. Preserve only the broad camera angle, pose, framing, and visual hierarchy needed for the concept. Do not reproduce logos, watermarks, brand identifiers, exact typography, distinctive copyrighted characters, or signature design elements. Photorealistic, high detail, realistic anatomy and hands, clean edges, no blur, pixelation, or compression artifacts.', 'growtype-art'),
            ],
            'pose-transfer' => [
                'label'  => __('Pose Transfer — New Subject & Scene', 'growtype-art'),
                'prompt' => __('Use the reference only for the broad pose, camera angle, and framing. Generate a clearly adult person with a completely different identity, facial structure, hair, body features, wardrobe, accessories, and expression. Build an entirely new setting with different architecture, furniture, props, lighting, time of day, and color palette. Do not copy the reference face, costume, text, logos, or distinctive design details. Produce a polished photorealistic editorial image with realistic anatomy, natural hands, detailed skin and hair, and clean high-resolution edges.', 'growtype-art'),
            ],
            'poster-reimagining' => [
                'label'  => __('Poster Reimagining — Preserve Hierarchy Only', 'growtype-art'),
                'prompt' => __('Create a materially original vertical promotional poster using only the reference image’s broad visual hierarchy as inspiration. Replace the character, background, wardrobe, props, lighting, palette, typography style, and decorative elements. Keep only a similar high-level subject placement and a clear lower text zone. Use original headline copy and a newly designed typographic treatment; do not reproduce the original wording, logo, watermark, brand identity, or signature visual elements. Premium photorealistic advertising finish, crisp details, realistic anatomy and hands, balanced spacing, and print-quality edges.', 'growtype-art'),
            ],
            'strong-original-variation' => [
                'label'  => __('Strong Original Variation — Change Most Details', 'growtype-art'),
                'prompt' => __('Reinterpret the reference concept as a new original image rather than a replica. Create a new clearly adult character, new pose variation, new camera distance, new environment, new wardrobe, new props, new lighting direction, and a distinct color palette. Preserve only the general theme or emotional intent. Avoid matching the original face, silhouette, exact composition, text, typography, logo, branding, or recognizable signature details. High-end photorealism, natural anatomy, accurate hands, detailed textures, cinematic lighting, and a clean high-resolution finish.', 'growtype-art'),
            ],
        ]);
    }

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
        $prompt_presets = self::get_prompt_presets();
        $image_size_presets = Growtype_Art_Admin_Content_Generator::get_image_size_presets();

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
                        <a id="gc-ref-lightbox"
                           href="<?php echo esc_url($reuse_image); ?>"
                           class="gh-lightbox-trigger"
                           aria-label="<?php esc_attr_e('Open reference image preview', 'growtype-art'); ?>">
                            <img id="gc-ref-thumb"
                                 src="<?php echo esc_url($reuse_image); ?>"
                                 alt="reference"
                                 class="gc-ref-thumb"
                                 onerror="this.style.opacity='.3'">
                        </a>
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
                        <div id="gc-model-price" style="margin-top:6px;font-size:12px;color:#64748b;" aria-live="polite">
                            <?php esc_html_e('Approx. price:', 'growtype-art'); ?>
                            <strong id="gc-model-price-value" style="color:#334155;">—</strong>
                        </div>
                    </div>

                </div>

                <!-- Image size -->
                <div class="gc-field" id="gc-image-size-wrap" style="margin-top:16px;<?php echo $first_type === 'image' ? '' : 'display:none;'; ?>">
                    <label class="gc-label" for="gc-image-size">
                        <?php esc_html_e('Image Size', 'growtype-art'); ?>
                    </label>
                    <select id="gc-image-size" class="gc-select">
                        <?php foreach ($image_size_presets as $key => $preset) : ?>
                            <option value="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($preset['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="gc-custom-size-wrap" style="display:none;margin-top:10px;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="gc-label" for="gc-custom-width"><?php esc_html_e('Width', 'growtype-art'); ?></label>
                            <input type="number" id="gc-custom-width" class="gc-input" value="768" min="256" max="4096" step="16" inputmode="numeric">
                        </div>
                        <div>
                            <label class="gc-label" for="gc-custom-height"><?php esc_html_e('Height', 'growtype-art'); ?></label>
                            <input type="number" id="gc-custom-height" class="gc-input" value="1024" min="256" max="4096" step="16" inputmode="numeric">
                        </div>
                    </div>
                    <p class="description" style="margin:6px 0 0;">
                        <?php esc_html_e('Auto uses the model’s native recommended dimensions and aspect ratio. Custom dimensions are normalized to multiples of 16 between 256 and 4096 pixels.', 'growtype-art'); ?>
                    </p>
                </div>

                <!-- No-providers notice (hidden by default) -->
                <div id="gc-no-providers" style="display:none;margin-top:14px;padding:12px 16px;background:#fef9c3;border:1px solid #fde047;border-radius:8px;font-size:13px;color:#713f12;">
                    <?php esc_html_e('No providers available for this content type yet.', 'growtype-art'); ?>
                </div>

                <!-- Default prompt preset -->
                <div class="gc-field" id="gc-default-prompt-wrap" style="margin-top:16px;<?php echo $first_type === 'image' ? '' : 'display:none;'; ?>">
                    <label class="gc-label" for="gc-default-prompt">
                        <?php esc_html_e('Default Prompt', 'growtype-art'); ?>
                        <span class="gc-label-hint"><?php esc_html_e('optional', 'growtype-art'); ?></span>
                    </label>
                    <select id="gc-default-prompt" class="gc-select">
                        <option value=""><?php esc_html_e('No preset — write a custom prompt…', 'growtype-art'); ?></option>
                        <?php foreach ($prompt_presets as $key => $preset) : ?>
                            <option value="<?php echo esc_attr($key); ?>"
                                    data-prompt="<?php echo esc_attr($preset['prompt']); ?>">
                                <?php echo esc_html($preset['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="margin:6px 0 0;">
                        <?php esc_html_e('Selecting a preset fills the editable prompt below. Presets encourage originality but do not guarantee copyright clearance.', 'growtype-art'); ?>
                    </p>
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
