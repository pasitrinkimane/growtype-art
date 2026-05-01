<?php
/**
 * Documentation Partial – Authentication & Credentials
 *
 * Reads the current admin user's WordPress Application Passwords,
 * renders them in a styled table, and shows a ready-to-use auth example.
 */

$current_user      = wp_get_current_user();
$app_passwords     = WP_Application_Passwords::get_user_application_passwords($current_user->ID);
$new_password_url  = admin_url('profile.php#application-passwords-section');
$base_url          = home_url('/wp-json/growtype-art/v1');
?>

<hr class="divider">

<section class="example-section" id="section-credentials">
    <h2>🔑 Authentication &amp; Credentials</h2>
    <p class="example-intro">
        All API endpoints require <strong>HTTP Basic Authentication</strong> using a WordPress
        <a href="<?php echo esc_url($new_password_url); ?>" target="_blank" rel="noopener"
           style="color:#818cf8;text-decoration:none;">Application Password</a>.
        Your username is <code style="font-family:var(--mono);font-size:12px;color:#a5b4fc"><?php echo esc_html($current_user->user_login); ?></code>
        and the password is one of the application passwords listed below.
        Only accounts with the <strong>admin</strong> login are authorised.
    </p>

    <!-- ── Available Application Passwords ── -->
    <?php if (!empty($app_passwords)) : ?>
    <div class="params-card" style="margin-bottom:20px;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Last Used</th>
                    <th>Last IP</th>
                    <th>Created</th>
                    <th>Usage hint</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($app_passwords as $i => $pwd) :
                    $name      = esc_html($pwd['name'] ?? '—');
                    $last_used = !empty($pwd['last_used']) ? esc_html(date('Y-m-d H:i', $pwd['last_used'])) : 'Never';
                    $last_ip   = esc_html($pwd['last_ip'] ?? '—');
                    $created   = !empty($pwd['created']) ? esc_html(date('Y-m-d', $pwd['created'])) : '—';
                    // uuid is stored; we show just the name since the raw password is not retrievable after creation
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:12px;"><?php echo $i + 1; ?></td>
                    <td><span class="param-name"><?php echo $name; ?></span></td>
                    <td class="param-desc"><?php echo $last_used; ?></td>
                    <td class="param-desc"><?php echo $last_ip; ?></td>
                    <td class="param-desc"><?php echo $created; ?></td>
                    <td>
                        <code style="font-family:var(--mono);font-size:11px;color:#94a3b8;">
                            -u '<?php echo esc_attr($current_user->user_login); ?>:&lt;<?php echo esc_html($name); ?>&gt;'
                        </code>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:10px;padding:14px 18px;margin-bottom:24px;font-size:13px;color:#86efac;">
        ℹ️ &nbsp;The raw password is only shown <strong>once</strong> at creation time.
        If you lost it, <a href="<?php echo esc_url($new_password_url); ?>" target="_blank" rel="noopener" style="color:#4ade80;">generate a new one</a> in your profile.
    </div>

    <?php else : ?>
    <div style="background:rgba(251,146,60,.08);border:1px solid rgba(251,146,60,.25);border-radius:10px;padding:18px 20px;margin-bottom:24px;font-size:14px;color:#fdba74;">
        ⚠️ &nbsp;No Application Passwords found for <strong><?php echo esc_html($current_user->user_login); ?></strong>.
        <a href="<?php echo esc_url($new_password_url); ?>" target="_blank" rel="noopener"
           style="color:#fb923c;font-weight:600;text-decoration:none;margin-left:6px;">
            → Create one in your profile
        </a>
    </div>
    <?php endif; ?>

    <!-- ── Auth Methods ── -->
    <div class="params-card" style="margin-bottom:24px;">
        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>How to send</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="param-name">Basic Auth</span></td>
                    <td class="param-desc"><code style="font-family:var(--mono);font-size:11px">Authorization: Basic base64(login:app_password)</code></td>
                    <td class="param-desc">Recommended for server-to-server. Use an <strong>Application Password</strong>, not your WP login password.</td>
                </tr>
                <tr>
                    <td><span class="param-name">Cookie / Nonce</span></td>
                    <td class="param-desc"><code style="font-family:var(--mono);font-size:11px">X-WP-Nonce: &lt;nonce&gt;</code></td>
                    <td class="param-desc">For authenticated browser sessions. Generate with <code style="font-family:var(--mono);font-size:11px">wp_create_nonce('wp_rest')</code>.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ── Example code cards ── -->
    <div class="example-grid">

        <!-- cURL -->
        <div class="code-card">
            <div class="code-card-header">Basic Auth &mdash; cURL <span class="lang-badge">Shell</span></div>
            <pre><span class="cc">curl</span> -X POST \
  <span class="cs"><?php echo esc_html($base_url . '/generate/character'); ?></span> \
  -u <span class="cs">'<?php echo esc_attr($current_user->user_login); ?>:YOUR_APPLICATION_PASSWORD'</span> \
  -H <span class="cs">'Content-Type: application/json'</span> \
  -d <span class="cs">'{"character_title":"Sophia Laurent","featured_in":"talkiemate"}'</span></pre>
        </div>

        <!-- PHP / wp_remote_post -->
        <div class="code-card">
            <div class="code-card-header">Basic Auth &mdash; PHP <span class="lang-badge">WordPress</span></div>
            <pre><span class="ck">$response</span> = wp_remote_post(
  <span class="cs">'<?php echo esc_html($base_url . '/generate/character'); ?>'</span>,
  [
    <span class="cs">'headers'</span> => [
      <span class="cs">'Authorization'</span> =>
        <span class="cs">'Basic '</span> . base64_encode(
          <span class="cs">'<?php echo esc_attr($current_user->user_login); ?>'</span>
          . <span class="cs">':'</span> . <span class="cs">'YOUR_APP_PASSWORD'</span>
        ),
      <span class="cs">'Content-Type'</span> => <span class="cs">'application/json'</span>,
    ],
    <span class="ck">'body'</span> => json_encode([
      <span class="cs">'character_title'</span> => <span class="cs">'Sophia Laurent'</span>,
      <span class="cs">'featured_in'</span>     => <span class="cs">'talkiemate'</span>,
    ]),
    <span class="ck">'timeout'</span> => <span class="cn">30</span>,
  ]
);

<span class="ck">$body</span> = json_decode(
  wp_remote_retrieve_body(<span class="ck">$response</span>), <span class="cb">true</span>
);</pre>
        </div>

        <!-- JavaScript fetch -->
        <div class="code-card">
            <div class="code-card-header">Basic Auth &mdash; JavaScript <span class="lang-badge">fetch</span></div>
            <pre><span class="ck">const</span> credentials = btoa(
  <span class="cs">'<?php echo esc_js($current_user->user_login); ?>:YOUR_APP_PASSWORD'</span>
);

<span class="ck">const</span> res = <span class="cb">await</span> fetch(
  <span class="cs">'<?php echo esc_js($base_url . '/generate/character'); ?>'</span>,
  {
    method:  <span class="cs">'POST'</span>,
    headers: {
      <span class="cs">'Authorization'</span>: <span class="cs">`Basic ${credentials}`</span>,
      <span class="cs">'Content-Type'</span>:  <span class="cs">'application/json'</span>,
    },
    body: JSON.stringify({
      character_title: <span class="cs">'Sophia Laurent'</span>,
      featured_in:     <span class="cs">'talkiemate'</span>,
    }),
  }
);

<span class="ck">const</span> data = <span class="cb">await</span> res.json();</pre>
        </div>

        <!-- Cookie / Nonce -->
        <div class="code-card">
            <div class="code-card-header">Cookie / Nonce &mdash; JavaScript <span class="lang-badge">Browser</span></div>
            <pre><span class="ck">const</span> nonce = <span class="cs">'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'</span>;
<span class="ck">// ↑ rendered server-side, valid for this session</span>

<span class="ck">const</span> res = <span class="cb">await</span> fetch(
  <span class="cs">'<?php echo esc_js($base_url . '/generate/character'); ?>'</span>,
  {
    method:      <span class="cs">'POST'</span>,
    credentials: <span class="cs">'same-origin'</span>,
    headers: {
      <span class="cs">'X-WP-Nonce'</span>:   nonce,
      <span class="cs">'Content-Type'</span>: <span class="cs">'application/json'</span>,
    },
    body: JSON.stringify({
      character_title: <span class="cs">'Sophia Laurent'</span>,
      featured_in:     <span class="cs">'talkiemate'</span>,
    }),
  }
);

<span class="ck">const</span> data = <span class="cb">await</span> res.json();</pre>
        </div>

    </div><!-- /.example-grid -->
</section>
