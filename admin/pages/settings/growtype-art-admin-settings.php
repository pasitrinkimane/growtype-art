<?php

class Growtype_Art_Admin_Settings
{
    public function __construct()
    {
        $this->load_tabs();

        add_action('admin_menu', array ($this, 'admin_menu_pages'));

        add_action('wp_loaded', array ($this, 'process_posted_data'));
    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu_pages()
    {
        /**
         * Options
         */
        add_submenu_page(
            'growtype-art',
            'Settings',
            'Settings',
            'manage_options',
            Growtype_Art_Admin::SETTINGS_PAGE_NAME,
            array ($this, 'options_page_content'),
            100
        );
    }

    /**
     * @return void
     */
    function options_page_content()
    {
        if (isset($_GET['page']) && $_GET['page'] == Growtype_Art_Admin::SETTINGS_PAGE_NAME) { ?>

            <div class="wrap">

                <h1>Growtype AI - settings</h1>

                <?php
                if (isset($_GET['updated']) && 'true' == esc_attr($_GET['updated'])) {
                    echo '<div class="updated" ><p>Theme Settings Updated.</p></div>';
                }

                if (isset ($_GET['tab'])) {
                    $this->render_settings_tab_render($_GET['tab']);
                } else {
                    $this->render_settings_tab_render();
                }
                ?>

                <form method="post" action="options.php">
                    <?php

                    if (isset ($_GET['tab'])) {
                        $tab = $_GET['tab'];
                    } else {
                        $tab = Growtype_Art_Admin::SETTINGS_DEFAULT_TAB;
                    }

                    switch ($tab) {
                        case 'general':
                            settings_fields('growtype_art_settings_general');

                            echo '<h3>Image generating settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_image_generating_settings');
                            echo '</table>';

                            break;
                        case 'openai':
                            settings_fields('growtype_art_settings_openai');

                            echo '<h3>Openai settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_openai_settings');
                            echo '</table>';

                            break;
                        case 'leonardo':
                            settings_fields('growtype_art_settings_leonardo');

                            echo '<h3>Leonardo AI settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_leonardoai_settings');
                            echo '</table>';

                            break;
                        case 'cloudinary':
                            settings_fields('growtype_art_settings_cloudinary');

                            echo '<h3>Cloudinary settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_cloudinary_settings');
                            echo '</table>';

                            break;
                        case 'replicate':
                            settings_fields('growtype_art_settings_replicate');

                            echo '<h3>Replicate settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_replicate_settings');
                            echo '</table>';

                            break;
                        case 'tinypng':
                            settings_fields('growtype_art_settings_tinypng');

                            echo '<h3>TinyPng settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_tinypng_settings');
                            echo '</table>';

                            break;
                        case 'optimization':
                            settings_fields('growtype_art_settings_optimization');

                            echo '<h3>Optimize database</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_optimization_settings');
                            echo '</table>';

                            break;
                        case 'evaluation':
                            settings_fields('growtype_art_settings_evaluation');

                            echo '<h3>Evaluate</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Art_Admin::SETTINGS_PAGE_NAME, 'growtype_art_evaluation_settings');
                            echo '</table>';

                            break;
                    }

                    if ($tab === 'optimization') {
                        echo '<input type="hidden" name="growtype_art_optimization" value="true" />';

                        submit_button('Optimize');
                    } elseif ($tab === 'evaluation') {
                        echo '<input type="hidden" name="growtype_art_evaluation" value="true" />';

                        submit_button('Evaluate');
                    } else {
                        submit_button();
                    }

                    ?>
                </form>
            </div>

            <?php
        }
    }

    function process_posted_data()
    {
        if (isset($_POST) && !empty($_POST)) {
            if (isset($_POST['growtype_art_optimization'])) {
                if (isset($_POST['growtype_art_optimization_clean_duplicate_settings'])) {
                    Growtype_Art_Database_Optimize::clean_duplicate_settings();
                }

                if (isset($_POST['growtype_art_optimization_clean_duplicate_images'])) {
                    Growtype_Art_Database_Optimize::clean_duplicate_images();
                }

                if (isset($_POST['growtype_art_optimization_sync_local_images'])) {
                    Growtype_Cron_Jobs::create_if_not_exists('generate-model-content', json_encode([
                        'action' => 'sync-local-images'
                    ]), 30);
                }

                if (isset($_POST['growtype_art_optimization_sync_models'])) {
                    Growtype_Art_Database_Optimize::sync_models();
                }

                if (isset($_POST['growtype_art_optimization_optimize_all_images'])) {
                    Growtype_Art_Database_Optimize::optimize_all_images();
                }

                if (isset($_POST['growtype_art_optimization_get_images_colors'])) {
                    Growtype_Art_Database_Optimize::get_images_colors();
                }

                if (isset($_POST['growtype_art_optimization_model_assign_categories'])) {
                    Growtype_Art_Database_Optimize::model_assign_categories();
                }

                if (isset($_POST['growtype_art_optimization_rename_images'])) {

                    for ($i = 10000; $i < 11000; $i++) {
                        // Retrieve the original images for the model
                        $model_details = growtype_art_get_model_images_grouped($i);
                        $original_images = array_reverse($model_details['original']);

                        if (empty($original_images)) {
                            continue;
                        }

                        // Define SEO and blacklisted keywords
                        $seo_keywords = [
                            'ai-chat',
                            'virtual-companion',
                            'digital-friend',
                            'interactive-chat',
                            'online-chat',
                            'ai-assistant',
                            'chat-bot',
                            'virtual-chat',
                            'digital-partner',
                            'smart-chat',
                            'ai-companion',
                            'virtual-agent',
                            'private-chat',
                            'virtual-buddy',
                            'ai-model',
                            'chat-room',
                            'digital-agent',
                            'chat-agent',
                            'virtual-avatar',
                            'virtual-partner',
                            'online-friend',
                            'interactive-bot',
                            'realistic-chat',
                            'ai-friend',
                            'virtual-date',
                            'chat',
                            'ai-girlfriend',
                            'ai-boyfriend',
                            'virtual-friend',
                            'digital-companion',
                            'ai-connection',
                            'chat-assistant',
                            'smart-assistant',
                            'ai-conversation',
                            'virtual-relationship',
                            'interactive-friend',
                            'ai-buddy',
                            'digital-chat'
                        ];

                        $blacklist_keywords = ['latex', 'kinky', 'handjob', 'lick', 'clothes', 'vibrator', 'horny', 'bdsm', 'vagina', 'undressing', 'dildo', 'erotic', 'sex', 'naked', 'cum', 'fuck', 'pussy', 'dick', 'nude', 'nipples', 'anus', 'sexy', 'lingerie', 'panties', 'ass', 'spreading', 'private', 'tits', 'sucking', 'suck', 'wet_shirt', 'wet-shirt', 'wet_shit'];

                        // Precompile the pattern to match blacklisted words with better boundaries
                        $pattern = '/(?:_|-|\b)(' . implode('|', array_map('preg_quote', $blacklist_keywords)) . ')(?:_|-|\b)/i';
                        $unwanted_pattern = '/[^a-z0-9-]+/';

                        // Prepare a collection for batch DB update
                        $batch_updates = [];

                        foreach ($original_images as $original_image) {
                            $name = $original_image['name'];
                            $local_path = growtype_art_image_local_path($original_image);

                            // Generate the potential .webp path
                            $webp_path = preg_replace('/\.[^.]+$/', '.webp', $local_path);

                            // Skip processing if neither the original nor .webp exist
                            if (!file_exists($local_path) && !file_exists($webp_path)) {
                                echo "❌ File not found: {$local_path} or {$webp_path}\n";
                                continue;
                            }

                            // Only rename if the name matches the blacklist pattern
                            if (preg_match($pattern, $name)) {
                                // Get the file extension
                                $extension = pathinfo($local_path, PATHINFO_EXTENSION);

                                // Generate an SEO-friendly, sanitized name
                                $clean_name = strtolower(preg_replace($unwanted_pattern, '-', pathinfo($name, PATHINFO_FILENAME)));

                                $pattern_clean_name = '/\b(' . implode('|', array_map('preg_quote', $blacklist_keywords)) . ')\b/i';

                                $clean_name = preg_replace($pattern_clean_name, '', $clean_name);

                                // Remove extra hyphens from the filename
                                $clean_name = preg_replace('/-+/', '-', $clean_name);
                                $clean_name = trim($clean_name, '-');

                                // Fallback if all words are blacklisted
                                if (empty($clean_name)) {
                                    $clean_name = 'virtual-companion-image';
                                }

                                // **Randomize and Deduplicate SEO Keywords**
                                shuffle($seo_keywords);
                                $selected_keywords = array_unique(array_slice($seo_keywords, 0, 2));
                                $seo_segment = implode('-', $selected_keywords);

                                // Remove duplicates from SEO segment
                                $seo_segment = preg_replace('/(\b\w+\b)(-\1)+/', '$1', $seo_segment);

                                // **Generate a Shorter Unique Hash**
                                $unique_hash = substr(md5($clean_name . microtime()), 0, 6);

                                // **Combine the SEO segment, clean name, and unique hash**
                                $final_name = "{$seo_segment}-{$unique_hash}-{$clean_name}";

                                // Trim any trailing hyphens
                                $final_name = rtrim($final_name, '-');

                                $final_name_words = explode('-', $final_name);

                                // Remove duplicates while maintaining order
                                $final_name_words = array_unique($final_name_words);

                                // Reconstruct the string
                                $final_name = implode('-', $final_name_words);

                                // **Final length check (max 60 chars)**
                                $final_name = substr($final_name, 0, 60);

                                // Generate new image paths
                                $new_image_path = dirname($local_path) . '/' . $final_name . '.' . $extension;
                                $new_webp_path = dirname($local_path) . '/' . $final_name . '.webp';

                                // **Rename original image if it exists**
                                $renamed_original = false;
                                if (file_exists($local_path) && rename($local_path, $new_image_path)) {
                                    $renamed_original = true;
                                    echo "✅ Image replaced successfully: {$new_image_path}\n";
                                }

                                // **Rename .webp image if it exists**
                                $renamed_webp = false;
                                if (file_exists($webp_path)) {
                                    if (rename($webp_path, $new_webp_path)) {
                                        $renamed_webp = true;
                                        echo "✅ WebP image replaced successfully: {$new_webp_path}\n";
                                    } else {
                                        echo "❌ Failed to replace .webp image: {$webp_path}\n";
                                    }
                                }

                                // If any image was renamed, update the database
                                if ($renamed_original || $renamed_webp) {
                                    $batch_updates[] = [
                                        'id' => $original_image['id'],
                                        'name' => $final_name,
                                        'path' => $renamed_webp ? $new_webp_path : $new_image_path
                                    ];
                                } else {
                                    echo "❌ Failed to replace image: {$local_path} or {$webp_path}\n";
                                }

                                Growtype_Art_Database_Crud::update_record(
                                    Growtype_Art_Database::IMAGES_TABLE,
                                    ['name' => $final_name],
                                    $original_image['id']
                                );

//                                d([
//                                    $i,
//                                    $name,
//                                    $seo_segment,
//                                    $clean_name,
//                                    $unique_hash,
//                                    $final_name
//                                ]);

                                // Debugging info
                                ddd([
                                    'id' => $i,
                                    'Image' => $original_image,
                                    'Original Path' => $local_path,
                                    'New Path' => $new_image_path,
                                    'WebP Path' => $webp_path,
                                    'New WebP Path' => $new_webp_path
                                ]);
                            }
                        }

//                        d('done one model');
                    }

                    d('done');
                }

                wp_redirect(admin_url('admin.php?page=growtype-art-settings&tab=optimization&updated=true'));
                exit();
            }

            if (isset($_POST['growtype_art_evaluation'])) {
                if (isset($_POST['growtype_art_evaluation_image_colors'])) {
                    $image_id = $_POST['growtype_art_evaluation_image_colors'];
                    echo Extract_Image_Colors_Job::get_image_details($image_id, true);
                    die();
                }

                wp_redirect(admin_url('admin.php?page=growtype-art-settings&tab=evaluation&updated=true'));
                exit();
            }
        }
    }

    function settings_tabs()
    {
        return apply_filters('growtype_art_admin_settings_tabs', []);
    }

    function render_settings_tab_render($current = Growtype_Art_Admin::SETTINGS_DEFAULT_TAB)
    {
        $tabs = $this->settings_tabs();

        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? ' nav-tab-active' : '';
            echo "<a class='nav-tab$class' href='?page=growtype-art-settings&tab=$tab'>$name</a>";

        }
        echo '</h2>';
    }

    public function load_tabs()
    {
        /**
         * Image generating settings
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/GeneralSettings.php';
        new GeneralSettings();

        /**
         * Leonardo ai settings
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/LeonardoAiSettings.php';
        new LeonardoAiSettings();

        /**
         * Cloudinary settings
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/CloudinarySettings.php';
        new CloudinarySettings();

        /**
         * Openai
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/OpenaiSettings.php';
        new OpenaiSettings();

        /**
         * Replicate
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/ReplicateSettings.php';
        new ReplicateSettings();

        /**
         * Tinypng
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/TinyPngSettings.php';
        new TinyPngSettings();

        /**
         * OptimizationSettings
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/OptimizationSettings.php';
        new OptimizationSettings();

        /**
         * EvaluationSettings
         */
        include_once GROWTYPE_ART_PATH . 'admin/pages/settings/tabs/EvaluationSettings.php';
        new EvaluationSettings();
    }
}
