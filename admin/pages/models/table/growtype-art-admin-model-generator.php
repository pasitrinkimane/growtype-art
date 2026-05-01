<?php

defined('ABSPATH') || exit;

class Growtype_Art_Admin_Model_Generator
{
    /**
     * @param Growtype_Art_Admin_Model_List_Table_Record $record_obj
     */
    public static function handle($record_obj)
    {
        if (isset($_POST['models-to-generate']) && !empty($_POST['models-to-generate'])) {
            self::process($record_obj);
        }

        self::render($record_obj);
    }

    /**
     * @param Growtype_Art_Admin_Model_List_Table_Record $record_obj
     */
    public static function process($record_obj)
    {
        $models_to_generate = explode(",", trim($_POST['models-to-generate']));
        $models_to_generate = array_map('trim', $models_to_generate);
        $provider = $_POST['model']['provider'][0] ?? '';
        $style = $_POST['model']['style'][0] ?? '';

        $info_message = '';

        foreach ($models_to_generate as $model_to_generate) {
            $model_slug = growtype_art_format_character_slug($model_to_generate);

            if (strlen($model_to_generate) + 5 < strlen($model_slug)) {
                $info_message .= sprintf(__('Model "%s" already exists.', 'growtype-art'), $model_to_generate) . " | ";
                continue;
            }

            $character_details = growtype_art_generate_character_details($model_to_generate);

            if (empty($character_details)) {
                $info_message .= sprintf(__('Model "%s" GPT details are empty.', 'growtype-art'), $model_to_generate) . "\r\n";
                continue;
            }

            $character_details['character_title'] = ucwords(strtolower($character_details['character_title']));

            $new_model_id = growtype_art_admin_duplicate_model(growtype_art_default_model_id_to_duplicate());

            Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                'prompt' => '((({character_style} style))) Generate ((full body)) image in {character_style} style of {character_title} {character_age} years old {character_ethnicity} {character_gender} {character_occupation}, {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, natural lighting, 35mm, f/2, 8K. Ensure visible skin texture for a {character_style} style portrayal. Utilize a {character_style} style with a 50mm lens for a balanced composition.',
            ], $new_model_id);

            if (!empty($provider)) {
                Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                    'provider' => $_POST['model']['provider'][0]
                ], $new_model_id);
            }

            if (!empty($style)) {
                $character_details['character_style'] = $style;
                if ($style === 'realistic') {
                    $character_details['categories'] = '{"People":{}}';
                } else {
                    $character_details['categories'] = '{"Anime & Manga":{}}';
                }
            }

            Openai_Base_Image::update_character_details($new_model_id, $character_details);

            growtype_art_admin_update_model_settings($new_model_id, [
                'generatable_images_limit' => (string) Growtype_Art_Crud::DEFAULT_GENERATABLE_IMAGES_LIMIT,
                'slug' => growtype_art_format_character_slug($character_details['character_title'], $new_model_id),
            ]);

            growtype_art_admin_update_bundle_keys([$new_model_id], 'add');
        }

        if (!empty($info_message)) {
            $record_obj->redirect_index([
                'message_type' => 'custom',
                'message' => $info_message,
                'status' => 'error',
            ]);
        }

        $record_obj->redirect_index([
            'message_type' => 'custom',
            'message' => __('Characters generated', 'growtype-art'),
        ]);
    }

    /**
     * @param Growtype_Art_Admin_Model_List_Table_Record $record_obj
     */
    public static function render($record_obj)
    {
        ?>
        <style>
            body {
                background: #020617 !important;
                background-image: 
                    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%) !important;
                background-attachment: fixed !important;
            }

            :root {
                --primary: #818cf8;
                --primary-hover: #6366f1;
                --bg-dark: #020617;
                --card-bg: rgba(15, 23, 42, 0.8);
                --border: rgba(255, 255, 255, 0.08);
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                --accent-glow: rgba(99, 102, 241, 0.15);
            }

            .advanced-gen-wrapper {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: var(--card-bg);
                backdrop-filter: blur(20px) saturate(180%);
                -webkit-backdrop-filter: blur(20px) saturate(180%);
                color: var(--text-main);
                padding: 48px;
                border-radius: 28px;
                margin: 24px 24px 24px 0;
                box-shadow: 
                    0 25px 50px -12px rgba(0, 0, 0, 0.7),
                    0 0 0 1px var(--border);
                position: relative;
                overflow: hidden;
            }

            .advanced-gen-wrapper::after {
                content: "";
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at top right, var(--accent-glow) 0%, transparent 40%);
                pointer-events: none;
            }

            .advanced-gen-header {
                margin-bottom: 32px;
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .advanced-gen-header h1 {
                font-size: 2.5rem;
                font-weight: 800;
                margin: 0;
                background: linear-gradient(to right, #fff, #94a3b8);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                letter-spacing: -0.025em;
            }

            .gen-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                margin-bottom: 32px;
            }

            .gen-control-group {
                background: rgba(0, 0, 0, 0.2);
                padding: 20px;
                border-radius: 16px;
                border: 1px solid var(--border);
                transition: all 0.3s ease;
            }

            .gen-control-group:hover {
                border-color: var(--primary);
                background: rgba(255, 255, 255, 0.08);
            }

            .gen-label {
                display: block;
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--text-muted);
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .gen-input-select {
                width: 100%;
                background: rgba(0, 0, 0, 0.2) !important;
                border: 1px solid var(--border) !important;
                color: #fff !important;
                border-radius: 8px !important;
                padding: 10px !important;
                height: 44px !important;
            }

            .gen-textarea-wrapper {
                grid-column: span 2;
            }

            .gen-textarea {
                width: 100%;
                background: rgba(0, 0, 0, 0.3) !important;
                border: 1px solid var(--border) !important;
                color: #fff !important;
                border-radius: 16px !important;
                padding: 20px !important;
                font-family: 'Fira Code', monospace;
                font-size: 1rem;
                line-height: 1.6;
                resize: vertical;
                transition: all 0.3s ease;
            }

            .gen-textarea:focus {
                outline: none !important;
                border-color: var(--primary) !important;
                box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2) !important;
            }

            .gen-submit-btn {
                background: var(--primary) !important;
                color: white !important;
                border: none !important;
                padding: 16px 32px !important;
                font-size: 1.1rem !important;
                font-weight: 700 !important;
                border-radius: 12px !important;
                cursor: pointer !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                width: fit-content !important;
                box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4) !important;
            }

            .gen-submit-btn:hover {
                background: var(--primary-hover) !important;
                transform: translateY(-2px) !important;
                box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.5) !important;
            }

            .gen-submit-btn.loading {
                opacity: 0.7;
                pointer-events: none;
            }

            .gen-brainstorm-btn {
                background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%) !important;
                color: white !important;
                border: none !important;
                padding: 6px 14px !important;
                font-size: 0.85rem !important;
                font-weight: 600 !important;
                border-radius: 8px !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
                transition: all 0.2s ease !important;
            }

            .gen-results-container {
                margin-top: 40px;
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                gap: 24px;
            }

            .character-card {
                background: rgba(30, 41, 59, 0.4);
                border: 1px solid var(--border);
                border-radius: 24px;
                padding: 28px;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                animation: slideUp 0.6s ease-out forwards;
                position: relative;
                overflow: hidden;
            }

            .character-card:hover {
                background: rgba(30, 41, 59, 0.6);
                transform: translateY(-8px);
                border-color: var(--primary);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            }

            .character-card::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 4px;
                height: 100%;
                background: var(--primary);
                opacity: 0.5;
            }

            @keyframes slideUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .spinner {
                border: 3px solid rgba(255,255,255,0.3);
                border-top: 3px solid #fff;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                animation: spin 1s linear infinite;
            }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

            /* Modal Styles */
            .gen-modal-overlay {
                position: fixed;
                top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.8);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 100000;
                backdrop-filter: blur(5px);
            }
            .gen-modal {
                background: #1e1e2e;
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 24px;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 20px 40px rgba(0,0,0,0.4);
                color: #fff;
            }
            .gen-modal-header { margin-bottom: 20px; text-align: center; }
            .gen-modal-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; }
            .gen-modal-body { margin-bottom: 24px; color: var(--text-muted); line-height: 1.5; }
            .gen-modal-footer { display: flex; flex-direction: column; gap: 10px; }
            .gen-modal-btn {
                padding: 12px;
                border-radius: 8px;
                border: 1px solid var(--border);
                background: rgba(255,255,255,0.05);
                color: #fff;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                text-align: center;
            }
            .gen-modal-btn:hover { background: rgba(255,255,255,0.1); }
            .gen-modal-btn-primary { background: var(--primary); border: none; }
            .gen-modal-btn-primary:hover { background: var(--primary-hover); }
            .gen-modal-btn-danger { color: #f87171; border-color: rgba(239,68,68,0.3); }
            .gen-modal-btn-danger:hover { background: rgba(239,68,68,0.1); }

            /* Details List Styles */
            .gen-details-list {
                display: grid;
                grid-template-columns: 140px 1fr;
                gap: 8px 16px;
                font-size: 0.85rem;
                max-height: 400px;
                overflow-y: auto;
                padding-right: 10px;
            }
            .gen-details-label { color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; align-self: start; padding-top: 2px; }
            .gen-details-value { color: #fff; line-height: 1.4; word-break: break-word; }
            
            .gen-details-raw {
                background: rgba(0,0,0,0.3);
                border-radius: 8px;
                padding: 16px;
                font-family: 'Monaco', 'Consolas', monospace;
                font-size: 0.8rem;
                color: #818cf8;
                white-space: pre-wrap;
                overflow-x: auto;
                max-height: 500px;
                border: 1px solid var(--border);
                line-height: 1.5;
            }
            .gen-details-raw::-webkit-scrollbar { width: 6px; }
            .gen-details-raw::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        </style>

        <div class="advanced-gen-wrapper">
            <div class="advanced-gen-header">
                <div style="background: var(--primary); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                </div>
                <h1>Advanced Generator</h1>
            </div>

            <div class="gen-grid">
                <div class="gen-control-group">
                    <label class="gen-label">Provider Engine</label>
                    <?= $record_obj::render_provider_select(Growtype_Art_Crud::DEFAULT_IMAGE_PROVIDER, ['class' => 'gen-input-select', 'id' => 'gen-provider']) ?>
                </div>
                
                <div class="gen-control-group">
                    <label class="gen-label">Artistic Style</label>
                    <?= $record_obj::render_select('id="gen-style"', 'realistic', [
                        'realistic' => 'Photorealistic (High Quality)',
                        'anime' => 'Anime & Manga (Stylized)'
                    ], false, ['class' => 'gen-input-select']) ?>
                </div>

                <div class="gen-control-group">
                    <label class="gen-label">Featured In</label>
                    <select id="gen-featured-in" class="gen-input-select" multiple style="height: auto; min-height: 40px;">
                        <?php foreach (growtype_art_get_model_featured_in_options() as $key => $label): ?>
                            <option value="<?= esc_attr($key) ?>"><?= esc_html($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gen-control-group">
                    <label class="gen-label">Created By</label>
                    <select id="gen-created-by" class="gen-input-select">
                        <?php foreach (growtype_art_get_model_users_options() as $key => $label): ?>
                            <option value="<?= esc_attr($key) ?>"><?= esc_html($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gen-control-group gen-textarea-wrapper">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                        <label class="gen-label" for="models-to-generate" style="margin-bottom: 0;">Models to Generate (One per line or comma separated)</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                                    <select id="gen-prompt-focus" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; height: 34px;">
                                        <option value="single" selected>Single Character</option>
                                        <option value="multiple">Multiple Characters</option>
                                    </select>
                                    <select id="gen-template" style="display:none; background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; height: 34px;">
                                        <option value="default" selected>Default List</option>
                                        <option value="universe">Same Universe / Shared Prefix</option>
                                    </select>
                                    <input type="text" id="gen-theme-hint" placeholder="Theme hint (e.g. Marvel...)" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; width: 220px; height: 34px;">
                                    <button type="button" id="gen-brainstorm-btn" class="gen-brainstorm-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                                        <span>Magic Brainstorm</span>
                                    </button>
                        </div>
                    </div>
                    <textarea id="models-to-generate" rows="12" class="gen-textarea" placeholder="E.g. Lara Croft, Tifa Lockhart, Wonder Woman..."></textarea>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 16px; margin-top: 8px;">
                <button type="button" id="gen-submit-ajax" class="gen-submit-btn">
                    <span>Generate Characters</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="color: var(--text-muted); font-size: 0.85rem; white-space: nowrap;">Amount per prompt</label>
                        <input type="number" id="gen-amount" min="1" max="20" value="1" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; border-radius: 6px; padding: 6px 10px; font-size: 0.9rem; width: 70px; text-align: center;">
                    </div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted); font-size: 0.85rem;">
                        <input type="checkbox" id="gen-review-toggle" style="accent-color: var(--primary);">
                        Review characters before generating
                    </label>
                </div>
            </div>

            <div id="gen-results" class="gen-results-container"></div>
        </div>

        <div id="gen-duplicate-modal" class="gen-modal-overlay">
            <div class="gen-modal">
                <div class="gen-modal-header">
                    <div class="gen-modal-title">Duplicate Character</div>
                    <div id="gen-duplicate-name" style="color: var(--primary); font-weight: 700;"></div>
                </div>
                <div class="gen-modal-body">
                    <p style="margin: 0 0 12px; color: var(--text-muted);">A character with this name already exists. What would you like to do?</p>
                    <div id="gen-existing-list" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px;max-height: 240px;overflow: scroll;"></div>
                </div>
                <div class="gen-modal-footer" style="flex-wrap: wrap; gap: 8px;">
                    <button class="gen-modal-btn gen-modal-btn-primary" id="gen-btn-update">Update Existing</button>
                    <button class="gen-modal-btn" id="gen-btn-force">Create New (Suffix)</button>
                    <button class="gen-modal-btn" id="gen-btn-review" style="background: rgba(255,255,255,0.05); color: #fff;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 4px;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        Info / Adjust
                    </button>
                    <button class="gen-modal-btn gen-modal-btn-danger" id="gen-btn-cancel">Skip</button>
                </div>
            </div>
        </div>

        <div id="gen-edit-modal" class="gen-modal-overlay" style="z-index: 1001111;">
            <div class="gen-modal" style="max-width: 850px; width: 95%;">
                <div class="gen-modal-header">
                    <div class="gen-modal-title">Adjust Character Details</div>
                </div>
                <div class="gen-modal-body" style="max-height: 70vh; overflow-y: auto; padding: 20px;">
                    <div id="gen-edit-form" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                        <!-- Form fields injected via JS -->
                    </div>
                </div>
                <div class="gen-modal-footer">
                    <button class="gen-modal-btn gen-modal-btn-primary" id="gen-edit-save">Save & Continue</button>
                    <button class="gen-modal-btn" id="gen-edit-cancel">Cancel</button>
                </div>
            </div>
        </div>

        <div id="gen-details-modal" class="gen-modal-overlay">
            <div class="gen-modal" style="max-width: 600px;">
                <div class="gen-modal-header">
                    <div class="gen-modal-title">Character Details</div>
                    <div id="gen-details-name" style="color: var(--primary); font-weight: 700;"></div>
                </div>
                <div class="gen-modal-body">
                    <pre id="gen-details-content" class="gen-details-raw"></pre>
                </div>
                <div class="gen-modal-footer">
                    <button class="gen-modal-btn gen-modal-btn-primary" onclick="document.getElementById('gen-details-modal').style.display='none'">Close</button>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const brainstormBtn = document.getElementById('gen-brainstorm-btn');
                const submitBtn = document.getElementById('gen-submit-ajax');
                const textarea = document.getElementById('models-to-generate');
                const themeHint = document.getElementById('gen-theme-hint');
                const styleSelect = document.getElementById('gen-style');
                const promptFocusSelect = document.getElementById('gen-prompt-focus');
                const templateSelect = document.getElementById('gen-template');
                const providerSelect = document.getElementById('gen-provider');
                const featuredInSelect = document.getElementById('gen-featured-in');
                const createdBySelect = document.getElementById('gen-created-by');
                const amountInput = document.getElementById('gen-amount');
                const resultsContainer = document.getElementById('gen-results');
                const ajaxUrl = '<?= admin_url('admin-ajax.php') ?>';
                const adminEditBase = '<?= admin_url('admin.php?page=growtype-art-models&action=edit&model=') ?>';
                const LS = 'growtype_art_gen_';

                // ── Restore saved values ──────────────────────────────────────
                const restore = () => {
                    const saved = JSON.parse(localStorage.getItem(LS + 'state') || '{}');
                    if (providerSelect    && saved.provider)     providerSelect.value    = saved.provider;
                    if (styleSelect       && saved.style)        styleSelect.value       = saved.style;
                    if (promptFocusSelect && saved.prompt_focus) promptFocusSelect.value = saved.prompt_focus;
                    if (templateSelect    && saved.template)     templateSelect.value    = saved.template;
                    if (createdBySelect   && saved.created_by)   createdBySelect.value   = saved.created_by;
                    if (themeHint         && saved.theme)        themeHint.value         = saved.theme;
                    if (amountInput       && saved.amount)       amountInput.value       = saved.amount;
                    if (textarea          && saved.prompt)       textarea.value          = saved.prompt;
                    if (featuredInSelect  && saved.featured_in) {
                        Array.from(featuredInSelect.options).forEach(opt => {
                            opt.selected = saved.featured_in.includes(opt.value);
                        });
                    }
                    if (promptFocusSelect) promptFocusSelect.dispatchEvent(new Event('change'));

                    if (saved.results && Array.isArray(saved.results)) {
                        // Reverse before prepending to maintain the saved order (top-to-bottom)
                        [...saved.results].reverse().forEach(char => renderCard(char));
                    }
                };
                const updatePlaceholder = () => {
                    if (!textarea) return;
                    const focus = promptFocusSelect ? promptFocusSelect.value : 'single';
                    const template = templateSelect ? templateSelect.value : 'default';

                    if (focus === 'multiple' && template === 'universe') {
                        textarea.placeholder = "E.g. Create Biblical figure: Jesus, Maria, John";
                    } else if (focus === 'multiple') {
                        textarea.placeholder = "E.g. Lara Croft, Tifa Lockhart, Wonder Woman...";
                    } else {
                        textarea.placeholder = "E.g. Lara Croft\nTifa Lockhart\nWonder Woman...";
                    }
                };

                if (promptFocusSelect) {
                    promptFocusSelect.addEventListener('change', () => {
                        if (templateSelect) {
                            templateSelect.style.display = promptFocusSelect.value === 'multiple' ? 'inline-block' : 'none';
                        }
                        updatePlaceholder();
                    });
                }
                
                if (templateSelect) {
                    templateSelect.addEventListener('change', updatePlaceholder);
                }

                restore();
                updatePlaceholder();

                // ── Persist on change ─────────────────────────────────────────
                const save = () => {
                    const state = {
                        provider:     providerSelect    ? providerSelect.value    : '',
                        style:        styleSelect       ? styleSelect.value       : '',
                        prompt_focus: promptFocusSelect ? promptFocusSelect.value : '',
                        template:     templateSelect    ? templateSelect.value    : '',
                        created_by:   createdBySelect   ? createdBySelect.value   : '',
                        theme:        themeHint         ? themeHint.value         : '',
                        amount:       amountInput       ? amountInput.value       : '1',
                        prompt:       textarea          ? textarea.value          : '',
                        featured_in:  featuredInSelect
                            ? Array.from(featuredInSelect.selectedOptions).map(o => o.value)
                            : [],
                        results: Array.from(document.querySelectorAll('.character-card')).map(card => {
                            const img = card.querySelector('img');
                            return {
                                model_id: card.dataset.modelId,
                                title: card.querySelector('div[style*="font-weight: 800"]').textContent,
                                occupation: card.querySelector('div[style*="color: var(--text-muted)"]').textContent,
                                description: card.querySelector('div[style*="font-size: 0.85rem"]').textContent,
                                image_url: img ? img.src : '',
                                metadata: JSON.parse(card.dataset.metadata || '{}')
                            };
                        })
                    };
                    localStorage.setItem(LS + 'state', JSON.stringify(state));
                };
                
                [providerSelect, styleSelect, promptFocusSelect, templateSelect, featuredInSelect, createdBySelect].forEach(el => {
                    el && el.addEventListener('change', save);
                });
                [themeHint, amountInput, textarea].forEach(el => {
                    el && el.addEventListener('input', save);
                });

                if (brainstormBtn) {
                    brainstormBtn.addEventListener('click', function() {
                        brainstormBtn.classList.add('loading');
                        const span = brainstormBtn.querySelector('span');
                        const originalText = span.textContent;
                        span.textContent = 'Thinking...';

                        const formData = new FormData();
                        formData.append('action', 'growtype_art_admin_generate_character_ideas');
                        formData.append('_ajax_nonce', '<?= wp_create_nonce('growtype_art_admin') ?>');
                        formData.append('style', styleSelect ? styleSelect.value : 'realistic');
                        formData.append('theme', themeHint ? themeHint.value : '');
                        formData.append('prompt_focus', promptFocusSelect ? promptFocusSelect.value : 'single');
                        formData.append('gen_template', templateSelect ? templateSelect.value : 'default');

                        fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.data.ideas) {
                                textarea.value = data.data.ideas;
                                save();
                            } else {
                                alert(data.data?.message || 'Brainstorm failed.');
                            }
                        })
                        .finally(() => {
                            brainstormBtn.classList.remove('loading');
                            span.textContent = originalText;
                        });
                    });
                }

                if (submitBtn) {
                    submitBtn.addEventListener('click', async function() {
                        const focus = promptFocusSelect ? promptFocusSelect.value : 'single';
                        const template = templateSelect ? templateSelect.value : 'default';
                        const raw = textarea.value;
                        
                        let names = [];
                        let universePrefix = '';

                        if (focus === 'multiple' && template === 'universe' && raw.includes(':')) {
                            const parts = raw.split(':');
                            universePrefix = parts[0].trim();
                            names = parts[1].split(',').map(n => n.trim()).filter(n => n);
                            
                            if (themeHint && !themeHint.value) {
                                // If it's a short universe name (1-3 words), use the whole thing as a tag
                                const words = universePrefix.split(' ').filter(w => w.length > 0);
                                if (words.length > 0 && words.length <= 3) {
                                    themeHint.value = universePrefix.toLowerCase().trim();
                                } else if (words.length > 0) {
                                    // Fallback to last significant word
                                    let tag = words[words.length - 1].toLowerCase().replace(/[^a-z0-9]/g, '');
                                    if (tag.length > 2) themeHint.value = tag;
                                }
                            }
                        } else {
                            names = focus === 'single'
                                ? raw.split('\n').map(n => n.trim()).filter(n => n)
                                : raw.split(',').map(n => n.trim()).filter(n => n);
                        }

                        if (!names.length) return alert('Please enter some names.');
 
                        const amount = Math.max(1, parseInt(amountInput ? amountInput.value : 1) || 1);
 
                        submitBtn.classList.add('loading');
                        const originalHtml = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span>Processing...</span><div class="spinner"></div>';
 
                        for (const name of names) {
                            const finalPrompt = universePrefix ? `${universePrefix} ${name}` : name;
                            for (let i = 0; i < amount; i++) {
                                await processCharacter(finalPrompt);
                            }
                        }

                        submitBtn.classList.remove('loading');
                        submitBtn.innerHTML = originalHtml;
                    });
                }

                async function processCharacter(name, options = {}) {
                    let formData = options.formData;
                    
                    if (!formData) {
                        formData = new FormData();
                        formData.append('action', 'growtype_art_admin_generate_single_character');
                        formData.append('_ajax_nonce', '<?= wp_create_nonce('growtype_art_admin') ?>');
                        formData.append('prompt', name);
                        formData.append('style', styleSelect ? styleSelect.value : 'realistic');
                        formData.append('prompt_focus', promptFocusSelect ? promptFocusSelect.value : 'single');
                        formData.append('provider', providerSelect ? providerSelect.value : '<?= Growtype_Art_Crud::DEFAULT_IMAGE_PROVIDER ?>');
                        formData.append('created_by', createdBySelect ? createdBySelect.value : 'admin');
                        formData.append('theme_hint', themeHint ? themeHint.value : '');
                        
                        if (options.overwrite) formData.append('overwrite', '1');
                        if (options.force_new) formData.append('force_new', '1');
    
                        if (featuredInSelect) {
                            Array.from(featuredInSelect.selectedOptions).forEach(opt => {
                                formData.append('featured_in[]', opt.value);
                            });
                        }
                    }

                    try {
                        const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
                        const data = await response.json();
                        let metadata = data.data?.generated_details || data.data?.create_params || data.data;

                        if (data.success) {
                            const reviewToggle = document.getElementById('gen-review-toggle');
                            if (reviewToggle && reviewToggle.checked && !options.is_retry) {
                                metadata = await showEditModal(metadata);
                                if (!metadata) return; // User cancelled
                                
                                // Finalize creation with adjusted details
                                formData.append('character_details_override', JSON.stringify(metadata));
                                const finalRes = await fetch(ajaxUrl, { method: 'POST', body: formData });
                                const finalData = await finalRes.json();
                                if (finalData.success) {
                                    renderCard(finalData.data);
                                    save();
                                }
                            } else {
                                renderCard(data.data);
                                save();
                            }
                        } else if (data.data?.code === 'duplicate_slug') {
                            const action = await showDuplicateModal(name, data.data.existing, metadata);
                            if (action) {
                                if (typeof action === 'object') {
                                    formData.append('character_details_override', JSON.stringify(action));
                                    formData.append('force_new', '1');
                                } else {
                                    formData.append(action, '1');
                                }
                                await processCharacter(name, { is_retry: true, formData });
                            }
                        } else {
                            console.error('Failed to generate:', name, data.data?.message);
                        }
                    } catch (e) {
                        console.error('Error generating:', name, e);
                    }
                }

                function showDuplicateModal(name, existing, generatedDetails) {
                    return new Promise(resolve => {
                        const modal = document.getElementById('gen-duplicate-modal');
                        const nameEl = document.getElementById('gen-duplicate-name');
                        const listEl = document.getElementById('gen-existing-list');
 
                        nameEl.textContent = name;
                        listEl.innerHTML = '';
 
                        const characters = Array.isArray(existing) ? existing : [existing];
                        characters.forEach(char => {
                            const item = document.createElement('div');
                            item.style.cssText = 'display:flex; align-items:center; gap: 12px; padding: 10px 12px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem;';
                            item.innerHTML = `
                                ${char.image_url ? `<div style="flex-shrink: 0; width: 40px; height: 40px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border);"><img src="${char.image_url}" style="width: 100%; height: 100%; object-fit: cover;"></div>` : `<div style="flex-shrink: 0; width: 40px; height: 40px; border-radius: 6px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); display:flex; align-items:center; justify-content:center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`}
                                <div style="flex-grow: 1;">
                                    <div style="font-weight: 700; color: #fff;">${char.title}</div>
                                    <div style="color: var(--text-muted); font-size: 0.75rem; margin-top: 2px;">slug: ${char.slug}</div>
                                </div>
                                <a href="<?= admin_url('admin.php?page=growtype-art-models&action=edit&model=') ?>${char.id}" target="_blank" style="color: var(--primary); font-size: 0.75rem; text-decoration: none;">View →</a>
                            `;
                            listEl.appendChild(item);
                        });
 
                        modal.style.display = 'flex';
 
                        const cleanup = (val) => {
                            modal.style.display = 'none';
                            btnUpdate.onclick = btnForce.onclick = btnReview.onclick = btnCancel.onclick = null;
                            resolve(val);
                        };
 
                        const btnUpdate = document.getElementById('gen-btn-update');
                        const btnForce = document.getElementById('gen-btn-force');
                        const btnReview = document.getElementById('gen-btn-review');
                        const btnCancel = document.getElementById('gen-btn-cancel');
 
                        btnUpdate.onclick = () => cleanup('overwrite');
                        btnForce.onclick = () => cleanup('force_new');
                        btnReview.onclick = async () => {
                            const adjusted = await showEditModal(generatedDetails);
                            if (adjusted) cleanup(adjusted);
                        };
                        btnCancel.onclick = () => cleanup(null);
                    });
                }

                function showEditModal(details) {
                    return new Promise(resolve => {
                        const modal = document.getElementById('gen-edit-modal');
                        const form = document.getElementById('gen-edit-form');
                        const btnSave = document.getElementById('gen-edit-save');
                        const btnCancel = document.getElementById('gen-edit-cancel');

                        form.innerHTML = '';
                        
                        const fields = [
                            { key: 'character_title', label: 'Character Title' },
                            { key: 'character_description', label: 'Description', type: 'textarea' },
                            { key: 'character_personality', label: 'Personality' },
                            { key: 'character_occupation', label: 'Occupation' },
                            { key: 'character_tags', label: 'Tags (comma separated)' },
                            { key: 'character_age', label: 'Age' },
                            { key: 'character_gender', label: 'Gender' },
                            { key: 'character_nationality', label: 'Nationality' },
                            { key: 'character_introduction', label: 'Introduction', type: 'textarea' },
                            { key: 'prompt', label: 'Visual Prompt', type: 'textarea', span: true }
                        ];

                        fields.forEach(field => {
                            const wrapper = document.createElement('div');
                            if (field.span) wrapper.style.gridColumn = '1 / -1';
                            
                            const val = details[field.key] || '';
                            wrapper.innerHTML = `
                                <label style="display:block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">${field.label}</label>
                                ${field.type === 'textarea' 
                                    ? `<textarea data-key="${field.key}" style="width:100%; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 8px; color: #fff; padding: 10px; font-size: 0.9rem; min-height: 80px; resize: vertical;">${val}</textarea>`
                                    : `<input type="text" data-key="${field.key}" value="${val}" style="width:100%; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 8px; color: #fff; padding: 10px; font-size: 0.9rem;">`
                                }
                            `;
                            form.appendChild(wrapper);
                        });

                        modal.style.display = 'flex';

                        btnSave.onclick = () => {
                            const newDetails = { ...details };
                            form.querySelectorAll('[data-key]').forEach(el => {
                                newDetails[el.dataset.key] = el.value;
                            });
                            modal.style.display = 'none';
                            resolve(newDetails);
                        };

                        btnCancel.onclick = () => {
                            modal.style.display = 'none';
                            resolve(null);
                        };
                    });
                }

                function renderCard(characterData) {
                    const modelId = characterData.model_id;
                    const imageUrl = characterData.image_url;
                    // Prioritize create_params for the "Info" view
                    const metadata = characterData.create_params || characterData.metadata || characterData;
                    
                    const card = document.createElement('div');
                    card.className = 'character-card';
                    card.dataset.modelId = modelId;
                    card.dataset.metadata = JSON.stringify(metadata);

                    card.innerHTML = `
                        <div style="display: flex; gap: 16px;">
                            ${imageUrl ? `<div style="flex-shrink: 0; width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border);"><img src="${imageUrl}" style="width: 100%; height: 100%; object-fit: cover;"></div>` : ''}
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 800; font-size: 1.25rem; margin-bottom: 4px;">${characterData.title}</div>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <div style="color: var(--text-muted); font-size: 0.9rem;">${characterData.occupation}</div>
                                    <div style="color: var(--primary); background: rgba(99, 102, 241, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-family: monospace;">${characterData.slug || metadata.slug}</div>
                                </div>
                                <div style="font-size: 0.85rem; line-height: 1.5; opacity: 0.8; margin-bottom: 12px;">${characterData.description || 'Character generated successfully.'}</div>
                                <div style="display: flex; gap: 8px;">
                                    <button onclick="showDetails(${modelId}, this.closest('.character-card'))" style="display:inline-flex; align-items:center; gap:5px; padding: 6px 14px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff; border-radius: 8px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                        Info
                                    </button>
                                    <a href="${adminEditBase}${modelId}" target="_blank" style="display:inline-flex; align-items:center; gap:5px; padding: 6px 14px; background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.4); color: var(--primary); border-radius: 8px; font-size: 0.8rem; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='rgba(99,102,241,0.3)'" onmouseout="this.style.background='rgba(99,102,241,0.15)'">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </a>
                                    <button onclick="deleteCharacter(${modelId}, this.closest('.character-card'))" style="display:inline-flex; align-items:center; gap:5px; padding: 6px 14px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #f87171; border-radius: 8px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.25)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    resultsContainer.prepend(card);
                }

                window.showDetails = function(modelId, cardEl) {
                    const metadata = JSON.parse(cardEl.dataset.metadata || '{}');
                    const modal = document.getElementById('gen-details-modal');
                    const nameEl = document.getElementById('gen-details-name');
                    const contentEl = document.getElementById('gen-details-content');
                    
                    nameEl.textContent = metadata.character_title || metadata.title || 'Character Details';
                    contentEl.textContent = JSON.stringify(metadata, null, 4);
                    
                    modal.style.display = 'flex';
                };

                window.deleteCharacter = function(modelId, cardEl) {
                    if (!confirm('Delete this character permanently?')) return;
                    const formData = new FormData();
                    formData.append('action', 'growtype_art_admin_delete_model');
                    formData.append('model_id', modelId);
                    formData.append('_ajax_nonce', '<?= wp_create_nonce('growtype_art_admin') ?>');
                    fetch(ajaxUrl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            cardEl.style.transition = 'opacity 0.3s';
                            cardEl.style.opacity = '0';
                            setTimeout(() => {
                                cardEl.remove();
                                save();
                            }, 300);
                        } else {
                            alert(data.data?.message || 'Delete failed.');
                        }
                    });
                };
            });
        </script>
        <?php
    }
}
