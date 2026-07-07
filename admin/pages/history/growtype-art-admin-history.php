<?php

defined('ABSPATH') || exit;

/**
 * Growtype_Art_Admin_History
 *
 * Admin page at: wp-admin/admin.php?page=growtype-art-history
 *
 * Reads from growtype_art_generations — populated by the
 * Growtype_Art_Generation_Logger via the `growtype_art_generation_saved` action.
 */
class Growtype_Art_Admin_History
{
    const PAGE_NAME = 'growtype-art-history';
    const PER_PAGE  = 40;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu_pages']);
        add_action('wp_ajax_growtype_art_admin_delete_generation', [$this, 'delete_generation_callback']);
    }

    public function delete_generation_callback()
    {
        check_ajax_referer('growtype_art_admin_history', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'growtype-art')], 403);
        }

        $generation_id = isset($_POST['generation_id']) ? (int)$_POST['generation_id'] : 0;
        if (!$generation_id || !self::table_ready()) {
            wp_send_json_error(['message' => __('Invalid generation.', 'growtype-art')], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . Growtype_Art_Database::GENERATIONS_TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, meta FROM {$table} WHERE id = %d", $generation_id), ARRAY_A);

        if (empty($row)) {
            wp_send_json_error(['message' => __('Generation not found.', 'growtype-art')], 404);
        }

        self::delete_history_file($row['meta'] ?? '');

        $deleted = $wpdb->delete($table, ['id' => $generation_id], ['%d']);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Could not delete generation.', 'growtype-art')], 500);
        }

        wp_send_json_success(['message' => __('Deleted.', 'growtype-art')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Menu registration
    // ─────────────────────────────────────────────────────────────────────────

    public function admin_menu_pages()
    {
        add_submenu_page(
            'growtype-art',
            __('Generation History', 'growtype-art'),
            __('History', 'growtype-art'),
            'manage_options',
            self::PAGE_NAME,
            [$this, 'render_page'],
            90
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table guard helper
    // ─────────────────────────────────────────────────────────────────────────

    private static function table_ready(): bool
    {
        global $wpdb;
        $t = $wpdb->prefix . Growtype_Art_Database::GENERATIONS_TABLE;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
    }

    private static function delete_history_file(string $meta_json): void
    {
        $meta = !empty($meta_json) ? json_decode($meta_json, true) : [];
        if (!is_array($meta) || empty($meta['history_file_url'])) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'] ?? '';
        $base_dir = $upload_dir['basedir'] ?? '';

        if (empty($base_url) || empty($base_dir) || strpos($meta['history_file_url'], $base_url) !== 0) {
            return;
        }

        $local_path = str_replace($base_url, $base_dir, $meta['history_file_url']);
        $history_dir = function_exists('growtype_art_get_upload_dir')
            ? growtype_art_get_upload_dir('history')
            : $base_dir . '/growtype-ai-uploads/history';

        $real_path = realpath($local_path);
        $real_history_dir = realpath($history_dir);

        if ($real_path && $real_history_dir && strpos($real_path, $real_history_dir) === 0 && is_file($real_path)) {
            @unlink($real_path);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Page render
    // ─────────────────────────────────────────────────────────────────────────

    public function render_page()
    {
        // Guard: table may not exist on a brand-new install before init fires.
        if (!self::table_ready()) {
            echo '<div class="wrap"><h1>' . esc_html__('Generation History', 'growtype-art') . '</h1>';
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__('The generations table does not exist yet. It will be created automatically on the next page load after the plugin initialises.', 'growtype-art');
            echo '</p></div></div>';
            return;
        }

        $base_url = admin_url('admin.php?page=' . self::PAGE_NAME);

        // ── Read & build filters ──────────────────────────────────────────────
        $filters     = $this->read_filters();
        $per_page    = $filters['limit'];
        $offset      = $filters['offset'];
        [$where_sql, $pvals] = $this->build_where($filters);
        $has_filters = ($filters['search'] || $filters['provider'] || $filters['status'] || $filters['created_by'] || $filters['date']);

        // ── Stats (always unfiltered, use custom_query for consistency) ────────
        $table = Growtype_Art_Database::GENERATIONS_TABLE;
        $stats = $this->get_stats($table);

        // ── Filtered total & rows via shared custom_query() ───────────────────
        $total = $this->get_filtered_count($table, $where_sql, $pvals);
        $rows  = $this->get_filtered_rows($table, $where_sql, $pvals, $per_page, $offset);

        // ── Distinct values for dropdowns (reuse helper) ──────────────────────
        $providers   = $this->get_distinct_col($table, 'provider');
        $statuses    = $this->get_distinct_col($table, 'status');
        $created_bys = $this->get_distinct_col($table, 'created_by');

        $total_pages = max(1, (int)ceil($total / $per_page));

        // Surface short local aliases for the template (avoids $filters['…'] noise in HTML)
        $search      = $filters['search'];
        $filter_prov = $filters['provider'];
        $filter_stat = $filters['status'];
        $filter_by   = $filters['created_by'];
        $filter_date = $filters['date'];

        $this->render_styles();
        ?>

        <div class="wrap" id="growtype-history-wrap">

            <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                🎨 <?php esc_html_e('Generation History', 'growtype-art'); ?>
                <span style="font-size:13px;color:#64748b;font-weight:400;background:#f1f5f9;padding:2px 10px;border-radius:50px;">
                    <?php echo number_format($total) . ' ' . esc_html(_n('record', 'records', $total, 'growtype-art')); ?>
                </span>
            </h1>
            <p style="color:#64748b;margin-top:0;">
                <?php esc_html_e('Every generation — from any provider — is recorded here automatically via the', 'growtype-art'); ?>
                <code>growtype_art_generation_saved</code> <?php esc_html_e('hook.', 'growtype-art'); ?>
            </p>

            <!-- ── Stat cards ─────────────────────────────────────────────── -->
            <div class="gh-stat-grid">
                <?php
                $stat_cards = [
                    ['label' => __('Total', 'growtype-art'),      'value' => number_format((int)($stats['total']   ?? 0)), 'cls' => ''],
                    ['label' => __('Successful', 'growtype-art'), 'value' => number_format((int)($stats['success'] ?? 0)), 'cls' => 'success'],
                    ['label' => __('Failed', 'growtype-art'),     'value' => number_format((int)($stats['failed']  ?? 0)), 'cls' => 'failed'],
                    ['label' => __('Pending', 'growtype-art'),    'value' => number_format((int)($stats['pending'] ?? 0)), 'cls' => 'pending'],
                ];
                $avg = (float)($stats['avg_duration'] ?? 0);
                $stat_cards[] = [
                    'label' => __('Avg Duration', 'growtype-art'),
                    'value' => $avg > 0 ? number_format($avg / 1000, 1) . 's' : '—',
                    'cls'   => '',
                ];
                foreach ($stat_cards as $card) { ?>
                    <div class="gh-stat-card">
                        <div class="gh-stat-label"><?php echo esc_html($card['label']); ?></div>
                        <div class="gh-stat-value <?php echo esc_attr($card['cls']); ?>"><?php echo $card['value']; /* already safe */ ?></div>
                    </div>
                <?php } ?>
            </div>

            <!-- ── Filters ────────────────────────────────────────────────── -->
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo self::PAGE_NAME; ?>">
                <div class="gh-filters">

                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                           placeholder="<?php esc_attr_e('Search prompt, title, meta…', 'growtype-art'); ?>"
                           style="min-width:220px;">

                    <select name="filter_provider">
                        <option value=""><?php esc_html_e('All Providers', 'growtype-art'); ?></option>
                        <?php foreach ($providers as $prov) { ?>
                            <option value="<?php echo esc_attr($prov); ?>" <?php selected($filter_prov, $prov); ?>>
                                <?php echo esc_html(ucfirst($prov)); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="filter_status">
                        <option value=""><?php esc_html_e('All Statuses', 'growtype-art'); ?></option>
                        <?php foreach ($statuses as $st) { ?>
                            <option value="<?php echo esc_attr($st); ?>" <?php selected($filter_stat, $st); ?>>
                                <?php echo esc_html(ucfirst($st)); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="filter_created_by">
                        <option value=""><?php esc_html_e('All Creators', 'growtype-art'); ?></option>
                        <?php foreach ($created_bys as $cb) { ?>
                            <option value="<?php echo esc_attr($cb); ?>" <?php selected($filter_by, $cb); ?>>
                                <?php echo esc_html($cb); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>"
                           title="<?php esc_attr_e('Filter by date', 'growtype-art'); ?>">

                    <button type="submit" class="gh-filter-btn"><?php esc_html_e('Filter', 'growtype-art'); ?></button>

                    <?php if ($has_filters) { ?>
                        <a href="<?php echo esc_url($base_url); ?>" class="gh-clear-link">
                            ✕ <?php esc_html_e('Clear', 'growtype-art'); ?>
                        </a>
                    <?php } ?>
                </div>
            </form>

            <!-- ── Table / empty state ────────────────────────────────────── -->
            <?php if (empty($rows)) { ?>

                <div class="gh-no-results">
                    <span style="font-size:40px;">📭</span><br>
                    <?php if ($has_filters) { ?>
                        <?php esc_html_e('No records match your filters.', 'growtype-art'); ?>
                        <br><a href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Clear filters', 'growtype-art'); ?></a>
                    <?php } else { ?>
                        <?php esc_html_e('No generation records yet.', 'growtype-art'); ?>
                        <br><small><?php esc_html_e('Records appear here automatically after the next generation.', 'growtype-art'); ?></small>
                    <?php } ?>
                </div>

            <?php } else { ?>

                <?php echo self::render_rows_table($rows); ?>

                <!-- Pagination — reuse Growtype_Art_Admin_Pages::render_pagination() -->
                <?php echo Growtype_Art_Admin_Pages::render_pagination(self::PAGE_NAME, $total, $offset, $per_page); ?>

            <?php } ?>
        </div>

        <?php self::render_table_scripts(); ?>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Read & sanitize all GET filter params into a canonical array.
     * Adding a new filter only requires one entry here.
     */
    private function read_filters(): array
    {
        return [
            'search'     => isset($_GET['s'])                 ? sanitize_text_field(wp_unslash($_GET['s']))                 : '',
            'provider'   => isset($_GET['filter_provider'])   ? sanitize_text_field(wp_unslash($_GET['filter_provider']))   : '',
            'status'     => isset($_GET['filter_status'])     ? sanitize_text_field(wp_unslash($_GET['filter_status']))     : '',
            'created_by' => isset($_GET['filter_created_by']) ? sanitize_text_field(wp_unslash($_GET['filter_created_by'])) : '',
            'date'       => isset($_GET['filter_date'])       ? sanitize_text_field(wp_unslash($_GET['filter_date']))       : '',
            // Pagination: match the offset/limit params generated by render_pagination()
            'offset'     => max(0, (int)($_GET['offset'] ?? 0)),
            'limit'      => max(1, (int)($_GET['limit'] ?? self::PER_PAGE)),
        ];
    }

    /**
     * Build a WHERE clause + prepared values from a filters array.
     * Adding a new filter column only requires one entry here.
     *
     * @return array{0: string, 1: array}  [$where_sql, $pvals]
     */
    private function build_where(array $filters): array
    {
        global $wpdb;

        $parts = ['1=1'];
        $vals  = [];

        if ($filters['search'] !== '') {
            $like    = '%' . $wpdb->esc_like($filters['search']) . '%';
            $parts[] = '(prompt LIKE %s OR character_title LIKE %s OR provider LIKE %s OR meta LIKE %s)';
            array_push($vals, $like, $like, $like, $like);
        }
        if ($filters['provider'] !== '') {
            $parts[] = 'provider = %s';
            $vals[]  = $filters['provider'];
        }
        if ($filters['status'] !== '') {
            $parts[] = 'status = %s';
            $vals[]  = $filters['status'];
        }
        if ($filters['created_by'] !== '') {
            $parts[] = 'created_by = %s';
            $vals[]  = $filters['created_by'];
        }
        if ($filters['date'] !== '') {
            $parts[] = 'DATE(created_at) = %s';
            $vals[]  = $filters['date'];
        }

        return [implode(' AND ', $parts), $vals];
    }

    /**
     * Fetch unfiltered aggregate stats via the shared custom_query() method.
     */
    private function get_stats(string $table): array
    {
        global $wpdb;
        $t   = esc_sql($wpdb->prefix . $table);
        $s   = Growtype_Art_Generation_Logger::STATUS_SUCCESS;
        $f   = Growtype_Art_Generation_Logger::STATUS_FAILED;
        $p   = Growtype_Art_Generation_Logger::STATUS_PENDING;
        $sql = "SELECT
                    COUNT(*)                                       AS total,
                    SUM(CASE WHEN status = '{$s}' THEN 1 ELSE 0 END) AS success,
                    SUM(CASE WHEN status = '{$f}'  THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = '{$p}' THEN 1 ELSE 0 END) AS pending,
                    AVG(CASE WHEN duration_ms > 0 THEN duration_ms ELSE NULL END) AS avg_duration
                FROM {$t}";

        $rows = Growtype_Art_Database_Crud::custom_query($sql);
        return $rows[0] ?? [];
    }

    /**
     * Return the count of rows matching the current WHERE clause.
     */
    private function get_filtered_count(string $table, string $where_sql, array $pvals): int
    {
        global $wpdb;
        $t   = esc_sql($wpdb->prefix . $table);
        $sql = "SELECT COUNT(*) AS n FROM {$t} WHERE {$where_sql}";
        $rows = Growtype_Art_Database_Crud::custom_query($sql, $pvals);
        return (int)($rows[0]['n'] ?? 0);
    }

    /**
     * Return the page of rows matching the current WHERE clause.
     */
    private function get_filtered_rows(string $table, string $where_sql, array $pvals, int $limit, int $offset): array
    {
        global $wpdb;
        $t    = esc_sql($wpdb->prefix . $table);
        $cols = 'id, model_id, image_id, character_title, prompt, provider, status, source, cost_usd, created_by, duration_ms, created_at, meta, content_type';
        $sql  = "SELECT {$cols} FROM {$t} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        return Growtype_Art_Database_Crud::custom_query($sql, array_merge($pvals, [$limit, $offset])) ?: [];
    }

    /**
     * Return distinct non-empty values for a single column.
     * Reuses custom_query() — no raw wpdb calls scattered around.
     */
    private function get_distinct_col(string $table, string $column): array
    {
        global $wpdb;
        $t   = esc_sql($wpdb->prefix . $table);
        $c   = esc_sql($column);
        $sql = "SELECT DISTINCT {$c} FROM {$t} WHERE {$c} IS NOT NULL AND {$c} != '' ORDER BY {$c}";
        $rows = Growtype_Art_Database_Crud::custom_query($sql);
        return array_column($rows, $column);
    }

    /**
     * Resolve the best available thumbnail URL for a row.
     * Public & static so it can be called from render_rows_table().
     */
    public static function resolve_thumbnail(array $row): string
    {
        $meta = !empty($row['meta']) ? json_decode($row['meta'], true) : [];
        $meta = is_array($meta) ? $meta : [];

        // 1. History copy (always preferred — it is permanent)
        if (!empty($meta['history_file_url'])) {
            return $meta['history_file_url'];
        }

        // 2. Direct image_id link
        if (!empty($row['image_id']) && function_exists('growtype_art_get_image_url')) {
            $url = growtype_art_get_image_url((int)$row['image_id']);
            if ($url) return $url;
        }

        // 3. Latest image for the model
        if (!empty($row['model_id']) && function_exists('growtype_art_get_model_images_grouped')) {
            $groups = growtype_art_get_model_images_grouped((int)$row['model_id'], 1);
            $latest = !empty($groups['original']) ? reset($groups['original']) : null;
            if ($latest && function_exists('growtype_art_get_image_url')) {
                $url = growtype_art_get_image_url($latest['id']);
                if ($url) return $url;
            }
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reusable table renderer
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render the generations table for the given rows.
     *
     * Public & static so any admin page can call it:
     *   echo Growtype_Art_Admin_History::render_rows_table($rows);
     *
     * @param array $rows Rows from get_filtered_rows() or render_recent().
     * @return string     Full <table> HTML.
     */
    public static function render_rows_table(array $rows): string
    {
        $known_statuses = [
            Growtype_Art_Generation_Logger::STATUS_SUCCESS,
            Growtype_Art_Generation_Logger::STATUS_FAILED,
            Growtype_Art_Generation_Logger::STATUS_PENDING,
        ];

        ob_start();
        ?>
        <table class="gh-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Preview', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Character', 'growtype-art'); ?></th>
                    <th class="gh-prompt-col"><?php esc_html_e('Prompt', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Provider', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Model', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Status', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Source', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Creator', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Cost', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Duration', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Date', 'growtype-art'); ?></th>
                    <th><?php esc_html_e('Actions', 'growtype-art'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) {
                    $image_url  = self::resolve_thumbnail($row);
                    $status     = $row['status'] ?? Growtype_Art_Generation_Logger::STATUS_UNKNOWN;
                    $status_cls = in_array($status, $known_statuses, true) ? $status : Growtype_Art_Generation_Logger::STATUS_UNKNOWN;
                    $ms         = (int)($row['duration_ms'] ?? 0);
                    $dur        = $ms > 0 ? ($ms >= 1000 ? number_format($ms / 1000, 1) . 's' : $ms . 'ms') : '—';
                    $model_url  = !empty($row['model_id'])
                        ? admin_url('admin.php?page=growtype-art-models&action=edit&model=' . (int)$row['model_id'])
                        : '';
                    $meta     = !empty($row['meta']) ? json_decode($row['meta'], true) : [];
                    $meta     = is_array($meta) ? $meta : [];
                    $is_video = (isset($meta['asset_type']) && $meta['asset_type'] === 'video')
                             || preg_match('/\.(mp4|webm|ogg|mov)$/i', $image_url);
                ?>
                <tr>
                    <td class="gh-id-col">#<?php echo (int)$row['id']; ?></td>

                    <td><?php if ($image_url) { ?>
                        <?php if ($is_video) { ?>
                            <a href="<?php echo esc_url($image_url); ?>" target="_blank" style="position:relative;display:block;width:44px;height:44px;">
                                <video src="<?php echo esc_url($image_url); ?>" class="gh-thumb" muted playsinline preload="metadata" style="background:#000;"></video>
                                <span style="position:absolute;bottom:2px;right:2px;background:rgba(0,0,0,0.6);color:#fff;font-size:9px;padding:1px 3px;border-radius:3px;line-height:1;font-family:-apple-system,sans-serif;">▶</span>
                            </a>
                        <?php } else { ?>
                            <a href="<?php echo esc_url($image_url); ?>" target="_blank">
                                <img src="<?php echo esc_url($image_url); ?>" class="gh-thumb" alt="" loading="lazy">
                            </a>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="gh-thumb-placeholder">
                            <?php echo $status === 'failed' ? '❌' : '🖼'; ?>
                        </div>
                    <?php } ?></td>

                    <td><?php if ($model_url) { ?>
                        <a href="<?php echo esc_url($model_url); ?>" class="gh-model-link">
                            <?php echo esc_html($row['character_title'] ?: __('(untitled)', 'growtype-art')); ?>
                        </a>
                        <div class="gh-sub">ID: <?php echo (int)$row['model_id']; ?></div>
                    <?php } else { ?>
                        <?php echo esc_html($row['character_title'] ?: '—'); ?>
                    <?php } ?></td>

                    <td class="gh-prompt-col">
                        <div class="gh-prompt-wrap">
                            <div class="gh-prompt-text" onclick="this.classList.toggle('expanded')" title="<?php esc_attr_e('Click to expand', 'growtype-art'); ?>">
                                <?php echo esc_html($row['prompt'] ?: '—'); ?>
                            </div>
                            <?php if (!empty($row['prompt'])) { ?>
                                <button type="button" class="gh-copy-btn"
                                        data-prompt="<?php echo esc_attr($row['prompt']); ?>"
                                        title="<?php esc_attr_e('Copy prompt', 'growtype-art'); ?>">
                                    📋
                                </button>
                            <?php } ?>
                        </div>
                    </td>

                    <td><span class="gh-provider-badge"><?php echo esc_html($row['provider'] ?: '—'); ?></span></td>

                    <td>
                        <?php if (!empty($meta['model_used'])) { ?>
                            <code style="font-size:11px;background:#f8fafc;padding:2px 6px;border-radius:4px;border:1px solid #e2e8f0;white-space:nowrap;">
                                <?php echo esc_html($meta['model_used']); ?>
                            </code>
                        <?php } else { ?>
                            <span style="color:#94a3b8;">—</span>
                        <?php } ?>
                    </td>

                    <td>
                        <span class="gh-badge <?php echo esc_attr($status_cls); ?>">
                            <?php echo esc_html(ucfirst($status)); ?>
                        </span>
                        <?php if ($status === 'failed' && !empty($meta['error'])) { ?>
                            <div class="gh-sub gh-error" title="<?php echo esc_attr($meta['error']); ?>">
                                <?php echo esc_html(mb_substr($meta['error'], 0, 40) . (mb_strlen($meta['error']) > 40 ? '…' : '')); ?>
                            </div>
                        <?php } ?>
                    </td>

                    <td class="gh-sub"><?php echo esc_html($row['source'] ?: '—'); ?></td>
                    <td class="gh-sub"><?php echo esc_html($row['created_by'] ?: '—'); ?></td>
                    <td class="gh-duration" style="font-family:monospace;font-size:12px;"><?php
                        $cost = isset($row['cost_usd']) && $row['cost_usd'] > 0
                            ? '$' . number_format((float)$row['cost_usd'], 4)
                            : '—';
                        echo esc_html($cost);
                    ?></td>
                    <td class="gh-duration"><?php echo esc_html($dur); ?></td>

                    <td style="font-size:12px;color:#64748b;white-space:nowrap;">
                        <?php echo esc_html(
                            !empty($row['created_at'])
                                ? date_i18n('Y-m-d H:i', strtotime($row['created_at']))
                                : '—'
                        ); ?>
                    </td>

                    <td class="gh-actions-col">
                        <?php if (!empty($row['prompt'])) {
                            $reuse_url = add_query_arg([
                                'page'                => Growtype_Art_Admin_Content::PAGE_NAME,
                                'reuse_prompt'        => rawurlencode($row['prompt']),
                                'reuse_provider'      => rawurlencode($row['provider'] ?? ''),
                                'reuse_model'         => rawurlencode($meta['model_used'] ?? ''),
                                'reuse_character_id'  => (int)($row['model_id'] ?? 0),
                                // content_type column added in v2 — fall back to meta['asset_type'] for old rows
                                'reuse_type'          => rawurlencode(
                                    !empty($row['content_type'])
                                        ? $row['content_type']
                                        : ($meta['asset_type'] ?? 'image')
                                ),
                                // Reference image — try each known key in order; guard against empty arrays
                                'reuse_image'         => rawurlencode(
                                    (!empty($meta['reference_image_urls']) && is_array($meta['reference_image_urls'])
                                        ? $meta['reference_image_urls'][0]
                                        : null)
                                    ?? (isset($meta['reference_image_url']) && $meta['reference_image_url'] !== ''
                                        ? $meta['reference_image_url']
                                        : null)
                                    ?? ($meta['init_image'] ?? '')
                                ),
                            ], admin_url('admin.php'));
                        ?>
                            <a href="<?php echo esc_url($reuse_url); ?>" class="gh-reuse-btn"
                               title="<?php esc_attr_e('Reuse this prompt in the Content Generator', 'growtype-art'); ?>">
                                ♻ <?php esc_html_e('Reuse', 'growtype-art'); ?>
                            </a>
                        <?php } ?>
                        <?php if (!empty($meta)) { ?>
                            <button type="button" class="gh-meta-btn"
                                    data-meta="<?php echo esc_attr(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>">
                                ⓘ
                            </button>
                        <?php } ?>
                        <button type="button"
                                class="gh-delete-btn"
                                data-id="<?php echo (int)$row['id']; ?>"
                                title="<?php esc_attr_e('Delete this generation', 'growtype-art'); ?>">
                            <?php esc_html_e('Delete', 'growtype-art'); ?>
                        </button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Fetch and render the last $limit generation records.
     *
     * Convenience wrapper — any admin page can call:
     *   echo Growtype_Art_Admin_History::render_recent(5);
     *
     * @param int $limit Number of rows to show (default 5).
     * @return string Full table HTML, or empty string if table doesn't exist.
     */
    public static function render_recent(int $limit = 5): string
    {
        if (!self::table_ready()) {
            return '';
        }

        global $wpdb;
        $t    = esc_sql($wpdb->prefix . Growtype_Art_Database::GENERATIONS_TABLE);
        $cols = 'id, model_id, image_id, character_title, prompt, provider, status, source, cost_usd, created_by, duration_ms, created_at, meta, content_type';
        $sql  = "SELECT {$cols} FROM {$t} ORDER BY created_at DESC LIMIT %d";
        $rows = Growtype_Art_Database_Crud::custom_query($sql, [$limit]) ?: [];

        if (empty($rows)) {
            return '';
        }

        return self::render_rows_table($rows);
    }

    /**
    /**
     * Outputs the shared CSS for gh-* table components.
     * Public & static — call it on any page that embeds the generations table.
     *
     * Usage: Growtype_Art_Admin_History::render_table_styles();
     */
    public static function render_table_styles(): void
    {
        ?>
        <style>
        /* ── gh-* Table (shared, unscoped) ─────────────── */
        .gh-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
        }
        .gh-table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 9px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .gh-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background .1s;
        }
        .gh-table tbody tr:last-child { border-bottom: none; }
        .gh-table tbody tr:hover { background: #f8fafc; }
        .gh-table td { padding: 9px 12px; vertical-align: middle; color: #374151; }

        .gh-id-col   { color: #94a3b8 !important; font-size: 11px; font-family: monospace; white-space: nowrap; }
        .gh-prompt-col { max-width: 280px; }
        .gh-sub      { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        .gh-error    { color: #b91c1c !important; }
        .gh-duration { font-family: monospace; font-size: 12px; color: #64748b; white-space: nowrap; }

        .gh-prompt-wrap {
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }
        .gh-prompt-text {
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 12px;
            cursor: pointer;
        }
        .gh-prompt-text.expanded { -webkit-line-clamp: unset; }

        /* ── Copy button ───────────────────────────────── */
        .gh-copy-btn {
            flex-shrink: 0;
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            padding: 0;
            color: #94a3b8;
            opacity: 0;
            transition: opacity .15s, background .12s, color .12s, border-color .12s;
            margin-top: 1px;
        }
        .gh-prompt-wrap:hover .gh-copy-btn { opacity: 1; }
        .gh-copy-btn:hover { background: #6366f1; border-color: #6366f1; color: #fff; }
        .gh-copy-btn.copied { background: #16a34a; border-color: #16a34a; color: #fff; opacity: 1; font-size: 11px; }

        /* ── Badges ────────────────────────────────────── */
        .gh-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
        }
        .gh-badge.success { background: #dcfce7; color: #15803d; }
        .gh-badge.failed  { background: #fee2e2; color: #b91c1c; }
        .gh-badge.pending { background: #fef9c3; color: #a16207; }
        .gh-badge.unknown { background: #f1f5f9; color: #64748b; }

        .gh-provider-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            padding: 2px 9px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            text-transform: lowercase;
        }

        /* ── Thumbnail ─────────────────────────────────── */
        .gh-thumb {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: block;
        }
        .gh-thumb-placeholder {
            width: 44px;
            height: 44px;
            background: #f1f5f9;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* ── Links ─────────────────────────────────────── */
        .gh-model-link { color: #6366f1; text-decoration: none; font-weight: 500; }
        .gh-model-link:hover { text-decoration: underline; }

        /* ── Meta button ───────────────────────────────── */
        .gh-meta-btn {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            color: #64748b;
            padding: 0;
            transition: background .12s, color .12s;
        }
        .gh-meta-btn:hover { background: #6366f1; color: #fff; border-color: #6366f1; }

        /* ── Actions cell ──────────────────────────────── */
        .gh-actions-col { white-space: nowrap; display: flex; align-items: center; gap: 6px; }

        /* ── Reuse button ──────────────────────────────── */
        .gh-reuse-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 2px 8px;
            height: 24px;
            font-size: 11px;
            font-weight: 600;
            color: #6366f1;
            text-decoration: none;
            white-space: nowrap;
            transition: background .12s, border-color .12s, color .12s;
            cursor: pointer;
        }
        .gh-reuse-btn:hover { background: #6366f1; border-color: #6366f1; color: #fff; }

        .gh-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: 1px solid #fecaca;
            border-radius: 5px;
            padding: 2px 8px;
            height: 24px;
            font-size: 11px;
            font-weight: 600;
            color: #dc2626;
            white-space: nowrap;
            transition: background .12s, border-color .12s, color .12s;
            cursor: pointer;
        }
        .gh-delete-btn:hover { background: #dc2626; border-color: #dc2626; color: #fff; }
        .gh-delete-btn:disabled { opacity: .55; pointer-events: none; }
        </style>
        <?php
    }

    /**
     * Outputs the shared meta-modal HTML + copy-btn/modal JS.
     * Public & static — call it on any page that embeds the generations table.
     *
     * Usage: Growtype_Art_Admin_History::render_table_scripts();
     */
    public static function render_table_scripts(): void
    {
        ?>
        <!-- gh-* meta detail modal -->
        <div id="gh-meta-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;padding:24px;max-width:540px;width:90%;max-height:80vh;overflow:auto;position:relative;">
                <button onclick="document.getElementById('gh-meta-modal').style.display='none'"
                        style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">✕</button>
                <h3 style="margin:0 0 14px;font-size:14px;color:#1e293b;"><?php esc_html_e('Generation Meta', 'growtype-art'); ?></h3>
                <pre id="gh-meta-content" style="font-size:12px;background:#f8fafc;padding:14px;border-radius:6px;overflow:auto;white-space:pre-wrap;word-break:break-all;color:#1e293b;border:1px solid #e2e8f0;"></pre>
            </div>
        </div>

        <script>
        // Guard: only initialise once per page — safe when render_table_scripts()
        // is called both on the history page AND via AJAX on the content-generator.
        if (!window._ghScriptsInit) {
            window._ghScriptsInit = true;
            window._ghHistoryNonce = '<?php echo esc_js(wp_create_nonce('growtype_art_admin_history')); ?>';
            window._ghAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

            // ── Meta modal — event delegation (works for AJAX-injected buttons) ─
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.gh-meta-btn');
                if (btn) {
                    var raw = btn.getAttribute('data-meta') || '';
                    try { raw = JSON.stringify(JSON.parse(raw), null, 2); } catch (err) {}
                    document.getElementById('gh-meta-content').textContent = raw;
                    document.getElementById('gh-meta-modal').style.display = 'flex';
                }
                // Close on backdrop click
                var modal = document.getElementById('gh-meta-modal');
                if (modal && e.target === modal) { modal.style.display = 'none'; }
            });

            // ── Copy-prompt buttons — event delegation ───────────────────────
            function ghFlashCopied(btn) {
                var orig = btn.textContent;
                btn.textContent = '✓';
                btn.classList.add('copied');
                setTimeout(function () { btn.textContent = orig; btn.classList.remove('copied'); }, 1500);
            }
            function ghLegacyCopy(text, btn) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (ex) {}
                document.body.removeChild(ta);
                ghFlashCopied(btn);
            }
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.gh-copy-btn');
                if (!btn) return;
                e.stopPropagation();
                var text = btn.getAttribute('data-prompt') || '';
                if (!text) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () { ghFlashCopied(btn); }).catch(function () { ghLegacyCopy(text, btn); });
                } else {
                    ghLegacyCopy(text, btn);
                }
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.gh-delete-btn');
                if (!btn) return;

                e.preventDefault();

                var generationId = btn.getAttribute('data-id');
                if (!generationId || !confirm('<?php echo esc_js(__('Delete this generation from history?', 'growtype-art')); ?>')) {
                    return;
                }

                btn.disabled = true;

                var data = new FormData();
                data.append('action', 'growtype_art_admin_delete_generation');
                data.append('_ajax_nonce', window._ghHistoryNonce);
                data.append('generation_id', generationId);

                fetch(window._ghAjaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                })
                    .then(function (response) { return response.json(); })
                    .then(function (response) {
                        if (!response || !response.success) {
                            btn.disabled = false;
                            alert(response && response.data && response.data.message ? response.data.message : 'Delete failed.');
                            return;
                        }

                        var row = btn.closest('tr');
                        if (row) row.remove();
                    })
                    .catch(function () {
                        btn.disabled = false;
                        alert('Delete failed.');
                    });
            });
        }
        </script>
        <?php
    }

    /**
     * Render all page CSS (history-page-specific layout + shared gh-* component styles).
     */
    private function render_styles()
    {
        self::render_table_styles();
        ?>
        <style>
        #growtype-history-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* ── Stat cards ────────────────────────────────── */
        .gh-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin: 18px 0 22px;
        }
        .gh-stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }
        .gh-stat-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 6px;
        }
        .gh-stat-value {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }
        .gh-stat-value.success { color: #16a34a; }
        .gh-stat-value.failed  { color: #dc2626; }
        .gh-stat-value.pending { color: #d97706; }

        /* ── Filters ───────────────────────────────────── */
        .gh-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .gh-filters input,
        .gh-filters select {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 5px 9px;
            font-size: 13px;
            color: #374151;
            background: #f8fafc;
            height: 32px;
        }
        .gh-filters input:focus,
        .gh-filters select:focus { border-color: #6366f1; outline: none; background: #fff; }
        .gh-filter-btn {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0 14px;
            height: 32px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background .15s;
        }
        .gh-filter-btn:hover { background: #4f46e5; }
        .gh-clear-link {
            font-size: 12px;
            color: #64748b;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            transition: background .12s;
        }
        .gh-clear-link:hover { background: #f1f5f9; color: #374151; }

        /* ── Empty state ───────────────────────────────── */
        .gh-no-results {
            text-align: center;
            padding: 56px 24px;
            color: #94a3b8;
            font-size: 15px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        </style>
        <?php
    }
}
