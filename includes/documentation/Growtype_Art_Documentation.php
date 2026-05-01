<?php

/**
 * Growtype Art – Documentation
 *
 * Registers a virtual front-end URL (/documentation) that is accessible
 * only to logged-in administrators. Renders a self-contained HTML page
 * that lists every REST API endpoint exposed by the plugin.
 *
 * Hook registration is done here so the class is entirely self-contained.
 */
class Growtype_Art_Documentation
{
    const SLUG = 'documentation';

    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_request']);
    }

    /**
     * Register the /documentation rewrite rule.
     */
    public function add_rewrite_rule()
    {
        add_rewrite_rule('^' . self::SLUG . '/?$', 'index.php?growtype_art_docs=1', 'top');
    }

    /**
     * Expose the custom query var to WordPress.
     */
    public function add_query_vars(array $vars): array
    {
        $vars[] = 'growtype_art_docs';
        return $vars;
    }

    /**
     * Intercept the request, gate it to admins, and render the docs page.
     */
    public function handle_request()
    {
        if (!get_query_var('growtype_art_docs')) {
            return;
        }

        if (!is_user_logged_in() || !current_user_can('administrator')) {
            wp_die(
                '<h1>Access Denied</h1><p>You must be logged in as an administrator to view this page.</p>',
                'Access Denied',
                ['response' => 403]
            );
        }

        $this->render();
        exit;
    }

    /**
     * Return the full list of API endpoints grouped by resource.
     */
    private function get_endpoints(): array
    {
        $base = home_url('/wp-json/growtype-art/v1');

        return [
            'Character' => [
                ['GET',  $base . '/retrieve/characters/{featured_in}',        'Retrieve characters (GET)'],
                ['POST', $base . '/retrieve/characters/{featured_in}/',        'Retrieve characters (POST)'],
                ['GET',  $base . '/retrieve/character/{featured_in}/{id}',     'Get single character by ID'],
                ['POST', $base . '/generate/character',                         'Generate / create a new character'],
                ['POST', $base . '/update/character',                           'Update all fields of an existing character'],
                ['POST', $base . '/generate/image',                             'Generate an image'],
                ['POST', $base . '/generate/character/image',                   'Generate image for a character'],
                ['POST', $base . '/generate/character/video',                   'Generate video for a character'],
                ['POST', $base . '/update/character/settings',                  'Update character visibility / priority (limited)'],
                ['POST', $base . '/upload/character/content',                   'Upload content for a character'],
            ],
            'Image' => [
                ['GET', $base . '/generate/{service}',  'Generate via a named service'],
                ['GET', $base . '/retrieve/images',     'List all images'],
                ['GET', $base . '/retrieve/image/{id}', 'Get single image by ID'],
            ],
            'Meal' => [
                ['POST', $base . '/get/mealplan',              'Get meal plan'],
                ['POST', $base . '/generate/mealplan/day',     'Generate a single day\'s meal plan'],
                ['POST', $base . '/generate/meal/{meal_type}', 'Generate a specific meal type'],
                ['GET',  $base . '/get/meal/{meal_slug}',      'Get a specific meal by slug'],
            ],
            'Model' => [
                ['GET', $base . '/retrieve/model/{id}', 'Get a model by ID'],
            ],
            'Color' => [
                ['GET', $base . '/retrieve/colors', 'Get all available colors'],
            ],
        ];
    }

    /**
     * Render the documentation HTML page.
     */
    private function render()
    {
        $endpoints = $this->get_endpoints();
        $site_name = get_bloginfo('name');
        $version   = defined('GROWTYPE_ART_VERSION') ? GROWTYPE_ART_VERSION : '—';
        $base_url  = home_url('/wp-json/growtype-art/v1');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation – <?php echo esc_html($site_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0d0f14;
            --bg-card:     #13161e;
            --bg-hover:    #1a1f2e;
            --border:      #1e2535;
            --accent:      #6366f1;
            --accent-glow: rgba(99,102,241,.25);
            --green:       #22c55e;
            --blue:        #3b82f6;
            --text:        #e2e8f0;
            --text-muted:  #64748b;
            --text-dim:    #94a3b8;
            --radius:      12px;
            --mono:        'JetBrains Mono', monospace;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #0d0f14 0%, #13161e 50%, #0a0c12 100%);
            border-bottom: 1px solid var(--border);
            padding: 48px 0 40px;
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: -60px; left: 50%; transform: translateX(-50%);
            width: 600px; height: 200px;
            background: radial-gradient(ellipse, var(--accent-glow) 0%, transparent 70%);
            pointer-events: none;
        }
        .header-inner {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 32px;
            position: relative;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--accent-glow);
            border: 1px solid rgba(99,102,241,.4);
            color: #a5b4fc;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 16px;
        }
        .badge::before { content: '●'; font-size: 8px; color: var(--green); }
        h1 {
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 700;
            letter-spacing: -.02em;
            background: linear-gradient(135deg, #e2e8f0 30%, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 15px;
        }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }
        .meta-chip {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            color: var(--text-dim);
        }
        .meta-chip strong { color: var(--text); font-weight: 600; }
        .meta-chip code {
            font-family: var(--mono);
            font-size: 11px;
            color: #a5b4fc;
        }

        /* ── Layout ── */
        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 48px 32px 80px;
        }

        /* ── Section ── */
        .section { margin-bottom: 40px; }
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .section-icon {
            width: 32px; height: 32px;
            background: var(--accent-glow);
            border: 1px solid rgba(99,102,241,.35);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        .section-count {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 100px;
            padding: 2px 10px;
        }

        /* ── Table ── */
        .table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: rgba(255,255,255,.03);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 10px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s ease;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--bg-hover); }
        td { padding: 13px 16px; vertical-align: middle; }

        /* method badge */
        .method {
            display: inline-block;
            font-family: var(--mono);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            padding: 3px 9px;
            border-radius: 6px;
            min-width: 46px;
            text-align: center;
        }
        .method-get  { background: rgba(34,197,94,.15);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
        .method-post { background: rgba(59,130,246,.15); color: #60a5fa; border: 1px solid rgba(59,130,246,.3); }

        /* url cell */
        .url-cell code {
            font-family: var(--mono);
            font-size: 12px;
            color: #c4b5fd;
            word-break: break-all;
        }
        .url-cell .param { color: #f9a8d4; }

        /* description */
        .desc { font-size: 13px; color: var(--text-dim); }

        /* ── Example blocks ── */
        .example-section { margin-bottom: 56px; }
        .example-section h2 { font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 6px; letter-spacing: -.01em; }
        .example-intro { font-size: 14px; color: var(--text-muted); margin-bottom: 24px; }
        .example-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 680px) { .example-grid { grid-template-columns: 1fr; } }
        .code-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .code-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 16px; background: rgba(255,255,255,.025); border-bottom: 1px solid var(--border);
            font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted);
        }
        .lang-badge { background: var(--accent-glow); border: 1px solid rgba(99,102,241,.35); color: #a5b4fc; padding: 2px 8px; border-radius: 4px; font-size: 10px; }
        .code-card pre { margin:0; padding:18px 20px; font-family:var(--mono); font-size:12px; line-height:1.75; color:#cbd5e1; overflow-x:auto; white-space:pre; }
        .ck { color:#94a3b8; } .cs { color:#a5b4fc; } .cn { color:#fb923c; } .cb { color:#34d399; } .cc { color:#6366f1; }
        .params-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-top: 20px; }
        .params-card table { width: 100%; border-collapse: collapse; }
        .params-card thead th { background:rgba(255,255,255,.025); font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--text-muted); padding:10px 16px; text-align:left; border-bottom:1px solid var(--border); }
        .params-card tbody tr { border-bottom: 1px solid var(--border); }
        .params-card tbody tr:last-child { border-bottom: none; }
        .params-card tbody tr:hover { background: var(--bg-hover); }
        .params-card td { padding: 11px 16px; font-size: 13px; vertical-align: top; }
        .param-name { font-family:var(--mono); font-size:12px; color:#c4b5fd; }
        .param-type { font-family:var(--mono); font-size:10px; color:#fb923c; background:rgba(251,146,60,.1); border:1px solid rgba(251,146,60,.25); padding:1px 6px; border-radius:4px; }
        .param-req { font-size:10px; font-weight:700; letter-spacing:.05em; }
        .param-req.req { color:#f87171; } .param-req.opt { color:var(--text-muted); }
        .param-desc { color: var(--text-dim); font-size: 13px; }
        .param-default { font-family:var(--mono); font-size:11px; color:#64748b; }
        .divider { border:none; border-top:1px solid var(--border); margin:48px 0; }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 32px;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 12px;
        }
        .footer a { color: #818cf8; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <div class="badge">Growtype Art v<?php echo esc_html($version); ?></div>
        <h1>API Documentation</h1>
        <p class="subtitle">Complete reference for all available REST API endpoints.</p>
        <div class="meta-row">
            <div class="meta-chip">Base URL&nbsp; <code><?php echo esc_html($base_url); ?></code></div>
            <div class="meta-chip">Auth&nbsp; <strong>Basic / Cookie</strong></div>
            <div class="meta-chip">Format&nbsp; <strong>JSON</strong></div>
            <div class="meta-chip">Site&nbsp; <strong><?php echo esc_html($site_name); ?></strong></div>
        </div>
    </div>
</header>

<div class="container">

<?php
        $icons = [
            'Character' => '🎭',
            'Image'     => '🖼',
            'Meal'      => '🍽',
            'Model'     => '🤖',
            'Color'     => '🎨',
        ];

        foreach ($endpoints as $group => $routes) :
            $icon = $icons[$group] ?? '📡';
            ?>
    <section class="section" id="section-<?php echo esc_attr(strtolower($group)); ?>">
        <div class="section-header">
            <div class="section-icon"><?php echo $icon; ?></div>
            <span class="section-title"><?php echo esc_html($group); ?></span>
            <span class="section-count"><?php echo count($routes); ?> endpoint<?php echo count($routes) !== 1 ? 's' : ''; ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px">Method</th>
                        <th>Endpoint</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as [$method, $url, $desc]) :
                        // Highlight {params} in the URL
                        $url_safe    = esc_html($url);
                        $url_display = preg_replace('/(\{[^}]+\})/', '<span class="param">$1</span>', $url_safe);
                        $method_class = 'method-' . strtolower($method);
                        ?>
                    <tr>
                        <td><span class="method <?php echo $method_class; ?>"><?php echo esc_html($method); ?></span></td>
                        <td class="url-cell"><code><?php echo $url_display; ?></code></td>
                        <td class="desc"><?php echo esc_html($desc); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/Section_Credentials.php'; ?>

<?php require __DIR__ . '/partials/Example_Create_Character.php'; ?>

</div>

<footer class="footer">
    Generated by <a href="https://growtype.com" target="_blank" rel="noopener">Growtype Art</a> &nbsp;·&nbsp;
    <?php echo esc_html(get_bloginfo('name')); ?> &nbsp;·&nbsp;
    <?php echo esc_html(current_time('Y')); ?>
</footer>

</body>
</html>
        <?php
    }
}
