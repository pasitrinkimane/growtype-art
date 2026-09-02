<?php

defined('ABSPATH') || exit;

/**
 * Growtype_Art_Generation_Logger
 *
 * Centralised, provider-agnostic service for recording every generation
 * event into the `growtype_art_generations` table.
 *
 * USAGE
 * ─────
 * The logger hooks into the WordPress action `growtype_art_generation_saved`
 * which is fired by Growtype_Art_Generator_Base::save_generations() — the
 * single convergence point for all providers (leonardoai, replicate, xai,
 * segmind, etc.).
 *
 * Any code that wants to log a generation without going through the normal
 * provider pipeline can call:
 *
 *   Growtype_Art_Generation_Logger::log([...]);
 *
 * Schema of the `growtype_art_generations` table:
 *   id, model_id, image_id, prompt, provider, status,
 *   created_by, character_title, duration_ms, meta,
 *   created_at, updated_at
 */
class Growtype_Art_Generation_Logger
{
    /** WordPress action name that providers fire after saving a generation. */
    const ACTION = 'growtype_art_generation_saved';

    /** Recognised status values. */
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';
    const STATUS_PENDING = 'pending';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Well-known source shortcuts — but `source` accepts ANY string.
     *
     * You can pass a domain name directly, e.g.:
     *   'source' => 'talkiemate.com'
     *   'source' => 'partner-site.io'
     *
     * Use these constants for first-party / internal origins.
     */
    const SOURCE_CHAT     = 'chat';
    const SOURCE_ADMIN    = 'admin';
    const SOURCE_API      = 'api';
    const SOURCE_FRONTEND = 'frontend';
    const SOURCE_CRON     = 'cron';

    /**
     * Field configuration for log() — defines cast/sanitizer per field.
     *
     * Format: 'field' => 'cast_type'
     *   int        → (int) cast
     *   string     → (string) cast
     *   sanitize   → sanitize_text_field()
     *   meta       → encode_meta()
     *
     * Adding a new column to the table only requires one entry here.
     */
    private const FIELD_CONFIG = [
        'model_id'        => 'int',
        'image_id'        => 'int',
        'duration_ms'     => 'int',
        'cost_usd'        => 'float',
        'prompt'          => 'string',
        'provider'        => 'sanitize',
        'status'          => 'sanitize',
        'source'          => 'sanitize',
        'created_by'      => 'sanitize',
        'character_title' => 'sanitize',
        'content_type'    => 'sanitize',   // 'image' | 'video' | 'text' | 'audio'
        'meta'            => 'meta',
    ];

    /**
     * Fields that may be updated via update().
     * Extend this list when the schema grows.
     */
    private const UPDATABLE_FIELDS = [
        'status', 'image_id', 'duration_ms', 'cost_usd', 'meta', 'provider', 'character_title', 'source', 'content_type',
    ];

    /**
     * Keys that must NEVER appear in the recorded request_payload.
     *
     * Uses a denylist instead of a whitelist so that new provider params are
     * captured automatically — just add secrets or internal keys here if needed.
     *
     * Categories:
     *   secrets   — credentials that must never be logged.
     *   system    — internal WP/plugin keys with no meaning to the provider.
     *   top-level — fields already stored as DB columns (see FIELD_CONFIG).
     *   meta-root — fields already surfaced at the root of the meta JSON.
     */
    private const PAYLOAD_DENY_KEYS = [
        // ── Secrets (never log) ───────────────────────────────────────
        'token', 'api_key', 'jwt_token', 'x_api_key', 'x-api-key',
        // ── Internal / system ─────────────────────────────────────────
        'generation_id', 'task_id', 'save_to_db',
        'enforce_output_dimensions',
        '_auto_dimensions',
        '_provider_request_payload',
        'model_id', 'api_group_key',
        // ── Already surfaced at root meta level ───────────────────────
        'asset_type', 'featured_in',
        // ── Redundant with model_used (all map to the same canonical field) ──
        'model', 'segmind_model', 'video_model',
        // ── Internal pipeline / config blobs (not provider-facing params) ────
        'providers',        // multi-provider routing config
        'images_amount',    // internal quantity control
        'prompt_params',    // already baked into the resolved prompt
        'reference_files',  // raw input format; reference_image_urls is the canonical form
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attach listeners once.
     * Called from the plugin's main bootstrap (e.g. class-growtype-art.php).
     */
    public static function init(): void
    {
        add_action('growtype_art_generation_success', [__CLASS__, 'handle_success'], 10, 4);
        add_action('growtype_art_generation_failed',  [__CLASS__, 'handle_failed'],  10, 4);
        add_action(self::ACTION,                      [__CLASS__, 'handle_generation_saved'], 10, 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Action handlers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hook handler for growtype_art_generation_success
     *
     * @param array       $saved_image Result of Growtype_Art_Crud::save_image().
     * @param int|null    $model_id
     * @param array       $params      Original generation params.
     * @param string      $provider    Provider key.
     */
    public static function handle_success(array $saved_image, $model_id, array $params, string $provider): void
    {
        if (!self::should_save($params)) {
            return;
        }

        $image_id      = $saved_image['id'] ?? $saved_image['image_id'] ?? null;
        $generation_id = $saved_image['generation_id'] ?? $params['generation_id'] ?? $params['task_id'] ?? null;
        $history_url   = self::copy_to_history($saved_image) ?: null;
        $meta          = self::prepare_generation_meta($params, $generation_id, $history_url);

        do_action(self::ACTION, self::build_event(
            self::STATUS_SUCCESS,
            $model_id,
            $params,
            $provider,
            $meta,
            $image_id
        ));
    }

    /**
     * Hook handler for growtype_art_generation_failed
     *
     * @param int|null $model_id
     * @param array    $params   Original generation params.
     * @param string   $error    Error message or code.
     * @param string   $provider Provider key.
     */
    public static function handle_failed($model_id, array $params, string $error, string $provider): void
    {
        if (!self::should_save($params)) {
            return;
        }

        $generation_id = $params['generation_id'] ?? $params['task_id'] ?? null;
        $meta          = self::prepare_generation_meta($params, $generation_id, null, $error);

        do_action(self::ACTION, self::build_event(
            self::STATUS_FAILED,
            $model_id,
            $params,
            $provider,
            $meta
        ));
    }

    /**
     * Listener for the `growtype_art_generation_saved` action.
     *
     * @param array $event {
     *   @type int|null    model_id        Model/character ID (nullable).
     *   @type int|null    image_id        Saved image ID (nullable).
     *   @type string      prompt          The final resolved prompt.
     *   @type string      provider        Provider key (e.g. 'xai', 'leonardoai').
     *   @type string      status          'success' | 'failed' | 'pending'.
     *   @type string|null source          Origin of the request — use SOURCE_* constants for
     *                                      first-party origins, or pass a domain string for
     *                                      external sites (e.g. 'talkiemate.com').
     *   @type string|null created_by      e.g. 'admin' | 'external_user' | WP username.
     *   @type string|null character_title Human-readable character/subject name.
     *   @type int|null    duration_ms     Wall-clock ms for the generation call.
     *   @type float|null  cost_usd        Generation cost in USD (from model config or provider response).
     *   @type array       meta            Arbitrary provider-specific extras.
     * }
     */
    public static function handle_generation_saved(array $event): void
    {
        self::log($event);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Write one generation record to the database.
     *
     * All fields are optional — pass only what you know.
     * Adding a new DB column only requires a matching entry in FIELD_CONFIG.
     *
     * @param array $data Same keys as the $event array documented above.
     * @return int|null   Inserted row ID, or null on failure.
     */
    public static function log(array $data): ?int
    {
        if (!self::table_exists()) {
            return null;
        }

        $row = [];
        foreach (self::FIELD_CONFIG as $field => $cast) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = self::sanitize_field($cast, $data[$field]);
            if ($value !== null) {
                $row[$field] = $value;
            }
        }

        // Ensure status always has a value.
        if (empty($row['status'])) {
            $row['status'] = self::STATUS_UNKNOWN;
        }

        $id = Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::GENERATIONS_TABLE, $row);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Update the status (and optionally other fields) of an existing
     * generation row. Useful when an async/pending job resolves.
     *
     * @param int   $generation_row_id The `id` in growtype_art_generations.
     * @param array $data              Fields to update (see UPDATABLE_FIELDS).
     */
    public static function update(int $generation_row_id, array $data): void
    {
        if ($generation_row_id <= 0 || !self::table_exists()) {
            return;
        }

        $update = [];
        foreach (self::UPDATABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $cast           = self::FIELD_CONFIG[$field] ?? 'string';
            $update[$field] = self::sanitize_field($cast, $data[$field]);
        }

        if (!empty($update)) {
            Growtype_Art_Database_Crud::update_record(
                Growtype_Art_Database::GENERATIONS_TABLE,
                $update,
                $generation_row_id
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-detect where the generation request came from.
     *
     * Priority:
     *   1. wp_doing_cron()           → 'cron'
     *   2. REST_REQUEST + Referer    → referer domain (e.g. 'talkiemate.com')
     *      REST_REQUEST alone        → 'api'
     *   3. is_admin()                → 'admin'
     *   4. HTTP_REFERER set          → referer domain (e.g. 'partner-site.io')
     *   5. fallback                  → 'frontend'
     *
     * Returns a string safe for storage — at most 100 chars.
     */
    private static function detect_source(): string
    {
        // 1. WP-Cron context
        if (wp_doing_cron() || (defined('DOING_CRON') && DOING_CRON)) {
            return self::SOURCE_CRON;
        }

        // Helper: extract clean domain from a URL
        $domain = static function (string $url): string {
            $host = wp_parse_url($url, PHP_URL_HOST) ?: '';
            // Strip 'www.' prefix
            return preg_replace('/^www\./i', '', strtolower($host));
        };

        $own_domain = $domain(home_url());

        // 2. REST API request
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url($_SERVER['HTTP_REFERER']) : '';
            if ($referer) {
                $ref_domain = $domain($referer);
                // External site calling the REST API
                if ($ref_domain && $ref_domain !== $own_domain) {
                    return substr($ref_domain, 0, 100);
                }
            }
            return self::SOURCE_API;
        }

        // 3. WordPress admin panel
        if (is_admin()) {
            return self::SOURCE_ADMIN;
        }

        // 4. Any HTTP referer (frontend/external)
        $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url($_SERVER['HTTP_REFERER']) : '';
        if ($referer) {
            $ref_domain = $domain($referer);
            if ($ref_domain && $ref_domain !== $own_domain) {
                return substr($ref_domain, 0, 100); // external site
            }
        }

        // 5. Default
        return self::SOURCE_FRONTEND;
    }

    /**
     * Build a canonical event array shared by handle_success() and handle_failed().
     */
    private static function build_event(
        string $status,
        $model_id,
        array  $params,
        string $provider,
        array  $meta,
        $image_id = null
    ): array {
        // Resolve content_type: explicit param > asset_type meta > default 'image'
        $asset_type   = $params['asset_type'] ?? $meta['asset_type'] ?? 'image';
        $content_type = match ($asset_type) {
            'video'  => 'video',
            'audio'  => 'audio',
            'text'   => 'text',
            default  => 'image',
        };

        return [
            'model_id'        => $model_id,
            'image_id'        => $image_id,
            'prompt'          => $params['prompt'] ?? '',
            'provider'        => $provider,
            'status'          => $status,
            'source'          => $params['source'] ?? self::detect_source(),
            'created_by'      => $params['created_by'] ?? null,
            'character_title' => $params['character_title'] ?? null,
            'duration_ms'     => $params['duration_ms'] ?? null,
            'cost_usd'        => isset($params['cost_usd']) ? (float) $params['cost_usd'] : null,
            'content_type'    => $content_type,
            'meta'            => $meta,
        ];
    }

    /**
     * Returns true when the event should be persisted to the DB.
     */
    private static function should_save(array $params): bool
    {
        return (bool) ($params['save_to_db'] ?? true);
    }

    /**
     * Apply the correct cast/sanitizer for a given field.
     *
     * @param string $cast  One of: int | string | sanitize | meta.
     * @param mixed  $value Raw value.
     * @return mixed        Sanitized value, or null when value is absent.
     */
    private static function sanitize_field(string $cast, $value)
    {
        if ($value === null) {
            return null;
        }

        switch ($cast) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'sanitize':
                return sanitize_text_field((string) $value);
            case 'meta':
                return self::encode_meta($value);
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * JSON-encode a meta value, returning '{}' for empty input.
     */
    private static function encode_meta($meta): string
    {
        if (empty($meta)) {
            return '{}';
        }
        return is_string($meta) ? $meta : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Check whether the generations table exists (guards against first-boot).
     */
    private static function table_exists(): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . Growtype_Art_Database::GENERATIONS_TABLE;
        return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }

    /**
     * Copy the generated asset to a permanent history folder.
     *
     * Strategy:
     *  1. Copy local file if path is known and accessible.
     *  2. Resolve a WP-uploads URL to a local path and copy.
     *  3. Fall back to downloading via HTTP.
     *
     * @param array $saved_image Result of Growtype_Art_Crud::save_image().
     * @return string Public URL of the copied history file, or empty string on failure.
     */
    private static function copy_to_history(array $saved_image): string
    {
        if (empty($saved_image) || !function_exists('growtype_art_get_upload_dir')) {
            return '';
        }

        $history_dir = growtype_art_get_upload_dir('history');
        if (!file_exists($history_dir)) {
            wp_mkdir_p($history_dir);
        }

        // Resolve a local file path (direct or via URL→path mapping).
        $local_path = self::resolve_local_path($saved_image);

        if (!empty($local_path)) {
            $result = self::copy_local_file($local_path, $history_dir);
            if ($result !== '') {
                return $result;
            }
        }

        // Fallback: download from public URL.
        $url = $saved_image['details']['url'] ?? $saved_image['url'] ?? '';
        return !empty($url) ? self::download_to_history($url, $history_dir) : '';
    }

    /**
     * Return a verified local filesystem path from $saved_image, or empty string.
     */
    private static function resolve_local_path(array $saved_image): string
    {
        $path = $saved_image['details']['path'] ?? '';

        if (!empty($path) && file_exists($path) && is_file($path)) {
            return $path;
        }

        // Try mapping a WP-uploads URL to a local path.
        $url = $saved_image['details']['url'] ?? $saved_image['url'] ?? '';
        if (empty($url)) {
            return '';
        }

        $upload_info = wp_upload_dir();
        $base_url    = $upload_info['baseurl'] ?? '';
        $base_dir    = $upload_info['basedir'] ?? '';

        if (!empty($base_url) && !empty($base_dir) && strpos($url, $base_url) === 0) {
            $resolved = str_replace($base_url, $base_dir, $url);
            if (file_exists($resolved) && is_file($resolved)) {
                return $resolved;
            }
        }

        return '';
    }

    /**
     * Copy a local file into $history_dir with a unique name.
     *
     * @return string Public URL of the copied file, or empty string on failure.
     */
    private static function copy_local_file(string $local_path, string $history_dir): string
    {
        $new_name = self::unique_filename(basename($local_path));
        $dest     = trailingslashit($history_dir) . $new_name;

        return copy($local_path, $dest)
            ? growtype_art_get_upload_dir_public('history') . '/' . $new_name
            : '';
    }

    /**
     * Download a remote asset into $history_dir.
     *
     * @return string Public URL of the downloaded file, or empty string on failure.
     */
    private static function download_to_history(string $url, string $history_dir): string
    {
        $response = wp_remote_get($url, [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return '';
        }

        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'downloaded_asset';
        $ext      = pathinfo($filename, PATHINFO_EXTENSION);

        if (empty($ext)) {
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            $ext          = (strpos($content_type, 'video') !== false) ? 'mp4' : 'jpg';
        }

        $new_name = self::unique_filename(pathinfo($filename, PATHINFO_FILENAME) . '.' . $ext);
        $dest     = trailingslashit($history_dir) . $new_name;

        return file_put_contents($dest, $body)
            ? growtype_art_get_upload_dir_public('history') . '/' . $new_name
            : '';
    }

    /**
     * Generate a unique filename by appending a timestamp + random suffix.
     *
     * @param string $filename Original filename (with extension).
     * @return string          e.g. "image_1718270400_aB3xYz.jpg"
     */
    private static function unique_filename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        return $name . '_' . time() . '_' . wp_generate_password(6, false, false) . '.' . $ext;
    }

    /**
     * Prepare full metadata for a generation event, merging non-sensitive request params.
     *
     * @param array       $params          Original generation params.
     * @param mixed       $generation_id   Provider-side generation/task ID.
     * @param string|null $history_file_url URL of the copied history asset.
     * @param string|null $error           Error message (failed events only).
     * @return array
     */
    private static function prepare_generation_meta(
        array   $params,
        $generation_id,
        ?string $history_file_url = null,
        ?string $error = null
    ): array {
        $meta = array_filter([
            'generation_id' => $generation_id,
            'asset_type'    => $params['asset_type'] ?? 'image',
            'model_used'    => $params['model'] ?? $params['segmind_model'] ?? $params['video_model'] ?? null,
            'api_group_key' => $params['api_group_key'] ?? null,
            'featured_in'   => $params['featured_in'] ?? null,
        ], fn($v) => $v !== null && $v !== '');  // strip null/empty noise

        if ($history_file_url !== null) {
            $meta['history_file_url'] = $history_file_url;
        }

        if ($error !== null) {
            $meta['error'] = $error;
        }

        // ── Request payload ──────────────────────────────────────────────────
        // Records what was sent to the provider API.
        //
        // Priority 1 — exact provider API body: if generate_image_init returned
        //   a '_request_payload' key (captured by the base class), use it verbatim.
        //   This gives maximum fidelity: exact field names, types and values the
        //   provider received.
        //
        // Priority 2 — dynamic denylist collection: every $param key that is NOT
        //   in PAYLOAD_DENY_KEYS or FIELD_CONFIG is included automatically.
        //   Future-proof: new provider params are captured without touching this file.
        if (!empty($params['_provider_request_payload']) && is_array($params['_provider_request_payload'])) {
            // Priority 1: use the exact body the provider posted to its API
            $meta['request_payload'] = $params['_provider_request_payload'];
        } else {
            // Priority 2: denylist approach — capture everything not secret/internal.
            // Future-proof: new provider params are included automatically.
            $deny = array_flip(array_merge(
                self::PAYLOAD_DENY_KEYS,
                array_keys(self::FIELD_CONFIG)  // prompt, provider, status, meta, cost_usd…
            ));

            $request_payload = array_diff_key($params, $deny);

            // Strip null / empty-string values; keep 0 and false (intentional params)
            $request_payload = array_filter(
                $request_payload,
                fn($v) => $v !== null && $v !== ''
            );

            if (!empty($request_payload)) {
                $meta['request_payload'] = $request_payload;
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        // Merge remaining non-sensitive params at root level for backward
        // compatibility (e.g. reference_image_urls used by the history page UI).
        // Uses PAYLOAD_DENY_KEYS as the single canonical source of excluded keys.
        $root_exclude = array_merge(
            array_keys(self::FIELD_CONFIG),
            self::PAYLOAD_DENY_KEYS
        );

        foreach ($params as $key => $val) {
            if (!in_array($key, $root_exclude, true) && !array_key_exists($key, $meta)) {
                $meta[$key] = $val;
            }
        }

        return $meta;
    }
}
