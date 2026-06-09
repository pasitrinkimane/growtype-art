<?php
/**
 * Documentation Partial – Image-to-Video Generation
 */

$base_url = home_url('/wp-json/growtype-art/v1');
?>

<hr class="divider">

<section class="example-section" id="example-generate-video">
    <h2>🎬 Image-to-Video Generation</h2>
    <p class="example-intro">
        Transform any reference image into a short video using AI.
        The endpoint
        <code style="font-family:var(--mono);font-size:12px;color:#a5b4fc">POST <?php echo esc_html($base_url . '/generate/character/video'); ?></code>
        uses <strong>Replicate</strong> with the <strong>Wan 2.2 i2v Fast</strong> model
        (<code style="font-family:var(--mono);font-size:11px">disable_safety_checker: true</code>).
        The request returns immediately with a <code style="font-family:var(--mono);font-size:11px">generation_id</code> —
        generation happens in the background via a cron job.
        Poll <code style="font-family:var(--mono);font-size:12px;color:#a5b4fc">GET <?php echo esc_html($base_url . '/retrieve/generation/{id}'); ?></code>
        to check status.
        Requires <strong>Basic Auth</strong> (admin-only).
    </p>

    <!-- Model Info -->
    <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:18px 20px;margin-bottom:24px;font-size:13px;">
        <strong style="color:#a5b4fc;">🔧 Model Configuration</strong>
        <table style="margin-top:10px;width:100%;font-size:12px;color:var(--text-dim);">
            <tr><td style="padding:3px 12px 3px 0;white-space:nowrap;color:var(--text-muted);">Model</td><td><code style="font-family:var(--mono);font-size:11px">wan-video/wan-2.2-i2v-fast</code></td></tr>
            <tr><td style="padding:3px 12px 3px 0;white-space:nowrap;color:var(--text-muted);">Resolution</td><td>480p</td></tr>
            <tr><td style="padding:3px 12px 3px 0;white-space:nowrap;color:var(--text-muted);">Frames</td><td>81 frames @ 16 fps (≈5 seconds)</td></tr>
            <tr><td style="padding:3px 12px 3px 0;white-space:nowrap;color:var(--text-muted);">Mode</td><td>Fast (<code style="font-family:var(--mono);font-size:11px">go_fast: true</code>)</td></tr>
            <tr><td style="padding:3px 12px 3px 0;white-space:nowrap;color:var(--text-muted);">Generation time</td><td>~60–120 seconds (processed via background cron)</td></tr>
            <tr><td style="padding:3px 12px 3px 0;white-space:nowrap;color:var(--text-muted);">Safety checker</td><td><code style="font-family:var(--mono);font-size:11px">disabled</code> — NSFW content is supported</td></tr>
        </table>
    </div>

    <!-- Example code cards -->
    <div class="example-grid">

        <!-- cURL -->
        <div class="code-card" style="grid-column:1/-1;">
            <div class="code-card-header">1. Generate Video &mdash; cURL <span class="lang-badge">Shell</span></div>
            <pre><span class="cc">curl</span> -X POST \
  <span class="cs"><?php echo esc_html($base_url . '/generate/character/video'); ?></span> \
  -u <span class="cs">'admin:YOUR_APPLICATION_PASSWORD'</span> \
  -H <span class="cs">'Content-Type: application/json'</span> \
  -d '{
    <span class="ck">"model_id"</span>:        <span class="cs">"5825"</span>,
    <span class="ck">"prompt"</span>:          <span class="cs">"woman stripping, seductive dance"</span>,
    <span class="ck">"providers"</span>:       [<span class="cs">"replicate"</span>],
    <span class="ck">"reference_image"</span>: {
      <span class="ck">"url"</span>: <span class="cs">"https://example.com/reference-image.jpg"</span>,
      <span class="ck">"id"</span>:  <span class="cn">209851</span>
    },
    <span class="ck">"types"</span>:          [<span class="cs">"private"</span>]
  }'</pre>
        </div>

        <!-- Generate Response -->
        <div class="code-card">
            <div class="code-card-header">Response — immediate <span class="lang-badge">JSON</span></div>
            <pre>{
  <span class="ck">"success"</span>:        <span class="cb">true</span>,
  <span class="ck">"prediction_id"</span>:  <span class="cs">"bsv4xxvcc5rmt0cymycazca9x0"</span>,
  <span class="ck">"generation_id"</span>:  <span class="cs">"01qtaGU8IP2e5FRtTC8eN3J5F..."</span>,
  <span class="ck">"status"</span>:         <span class="cs">"starting"</span>,
  <span class="ck">"message"</span>:        <span class="cs">"Video generation queued."</span>
}</pre>
        </div>

        <!-- Poll status -->
        <div class="code-card" style="grid-column:1/-1;">
            <div class="code-card-header">2. Poll Status &mdash; cURL <span class="lang-badge">Shell</span></div>
            <pre><span class="cc">curl</span> -u <span class="cs">'admin:YOUR_APPLICATION_PASSWORD'</span> \
  <span class="cs"><?php echo esc_html($base_url . '/retrieve/generation/01qtaGU8IP2e5FRtTC8eN3J5F...'); ?></span></pre>
            <div style="margin-top:14px;padding:0;font-size:13px;color:var(--text-dim);">
                <strong>Loop until complete</strong> — poll every 10 seconds:
            </div>
            <pre style="margin-top:8px;"><span class="cc">#!/bin/bash</span>
GENERATION_ID=<span class="cs">"01qtaGU8IP2e5FRtTC8eN3J5F..."</span>

<span class="cb">while</span> true; <span class="cb">do</span>
  STATUS=$(<span class="cc">curl</span> -s -u <span class="cs">'admin:PASSWORD'</span> \
    <span class="cs">"<?php echo esc_html($base_url . '/retrieve/generation/'); ?>$GENERATION_ID"</span>)
  echo <span class="cs">"$STATUS"</span> | grep <span class="cs">'"status":"completed"'</span> && break
  sleep 10
<span class="cb">done</span></pre>
        </div>

        <!-- Status responses -->
        <div class="code-card">
            <div class="code-card-header">Poll Response — processing <span class="lang-badge">JSON</span></div>
            <pre>{
  <span class="ck">"success"</span>: <span class="cb">false</span>,
  <span class="ck">"status"</span>:  <span class="cs">"processing"</span>,
  <span class="ck">"message"</span>: <span class="cs">"Generation is still processing or not found"</span>
}</pre>
        </div>

        <div class="code-card">
            <div class="code-card-header">Poll Response — completed <span class="lang-badge">JSON</span></div>
            <pre>{
  <span class="ck">"success"</span>:       <span class="cb">true</span>,
  <span class="ck">"status"</span>:        <span class="cs">"completed"</span>,
  <span class="ck">"generation_id"</span>: <span class="cs">"01qtaGU8IP2e5FRtTC8eN3J5F..."</span>,
  <span class="ck">"image_id"</span>:      <span class="cn">211657</span>,
  <span class="ck">"url"</span>:           <span class="cs">"https://media.example.com/.../video.mp4"</span>
}</pre>
        </div>

        <!-- PHP polling example -->
        <div class="code-card">
            <div class="code-card-header">Poll for completion &mdash; PHP <span class="lang-badge">WordPress</span></div>
            <pre><span class="cc">// 1. Submit generation</span>
<span class="ck">$response</span> = wp_remote_post(
  <span class="cs">'<?php echo esc_html($base_url . '/generate/character/video'); ?>'</span>,
  [
    <span class="cs">'headers'</span> => [
      <span class="cs">'Authorization'</span> => <span class="cs">'Basic '</span> . base64_encode(<span class="cs">'admin:PASSWORD'</span>),
      <span class="cs">'Content-Type'</span>  => <span class="cs">'application/json'</span>,
    ],
    <span class="ck">'body'</span> => json_encode([
      <span class="cs">'model_id'</span>        => <span class="cn">5825</span>,
      <span class="cs">'prompt'</span>          => <span class="cs">'some prompt'</span>,
      <span class="cs">'providers'</span>       => [<span class="cs">'replicate'</span>],
      <span class="cs">'reference_image'</span> => [<span class="cs">'url'</span> => <span class="cs">'https://...'</span>],
      <span class="cs">'types'</span>          => [<span class="cs">'private'</span>],
    ]),
    <span class="ck">'timeout'</span> => <span class="cn">30</span>,
  ]
);
<span class="ck">$result</span> = json_decode(wp_remote_retrieve_body(<span class="ck">$response</span>), <span class="cb">true</span>);
<span class="ck">$gen_id</span> = <span class="ck">$result</span>[<span class="cs">'generation_id'</span>];

<span class="cc">// 2. Poll until complete</span>
<span class="cb">do</span> {
  sleep(10);
  <span class="ck">$status</span> = json_decode(wp_remote_retrieve_body(
    wp_remote_get(<span class="cs">"<?php echo esc_html($base_url); ?>/retrieve/generation/{$gen_id}"</span>, [
      <span class="cs">'headers'</span> => [<span class="cs">'Authorization'</span> => <span class="cs">'Basic '</span> . base64_encode(<span class="cs">'admin:PASSWORD'</span>)],
    ])
  ), <span class="cb">true</span>);
} <span class="cb">while</span> (<span class="ck">$status</span>[<span class="cs">'status'</span>] !== <span class="cs">'completed'</span>);

<span class="ck">$video_url</span> = <span class="ck">$status</span>[<span class="cs">'url'</span>]; <span class="cc">// Done!</span></pre>
        </div>

    </div><!-- /.example-grid -->

    <!-- Parameters table (POST) -->
    <div class="params-card">
        <table>
            <thead>
                <tr>
                    <th colspan="5" style="font-size:14px;color:var(--text);text-transform:none;letter-spacing:0;">
                        📤 POST <?php echo esc_html($base_url . '/generate/character/video'); ?> — Parameters
                    </th>
                </tr>
                <tr>
                    <th>Parameter</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th>Default</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
<?php
$doc_params = [
    ['model_id',        'integer', true,  '—',       'ID of the model/character to associate the video with.'],
    ['prompt',          'string',  false, 'model prompt', 'Text prompt describing the desired video motion. Falls back to the model\'s default prompt.'],
    ['providers',       'array',   false, '["replicate"]', 'List of AI providers to use. Currently supports <code style="font-family:var(--mono);font-size:11px">["replicate"]</code>.'],
    ['reference_image', 'object',  true,  '—',       'The source image to animate, with <code style="font-family:var(--mono);font-size:11px">url</code> (required) and <code style="font-family:var(--mono);font-size:11px">id</code> (optional).'],
    ['types',           'array',   false, '[]',      'Tags applied to the generated video (e.g. <code style="font-family:var(--mono);font-size:11px">["private"]</code>). Stored as image meta.'],
];

foreach ($doc_params as $p) :
    [$name, $type, $required, $default, $desc] = $p;
?>
                <tr>
                    <td><span class="param-name"><?php echo esc_html($name); ?></span></td>
                    <td><span class="param-type"><?php echo esc_html($type); ?></span></td>
                    <td><span class="param-req <?php echo $required ? 'req' : 'opt'; ?>"><?php echo $required ? 'REQUIRED' : 'optional'; ?></span></td>
                    <td><span class="param-default"><?php echo esc_html($default); ?></span></td>
                    <td class="param-desc"><?php echo $desc; ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Status endpoint reference -->
    <div class="params-card" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th colspan="3" style="font-size:14px;color:var(--text);text-transform:none;letter-spacing:0;">
                        📥 GET <?php echo esc_html($base_url . '/retrieve/generation/{generation_id}'); ?> — Status Endpoint
                    </th>
                </tr>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><span class="param-name">success</span></td><td><span class="param-type">boolean</span></td><td class="param-desc"><code style="font-family:var(--mono);font-size:11px">true</code> when generation is complete, <code style="font-family:var(--mono);font-size:11px">false</code> when still processing.</td></tr>
                <tr><td><span class="param-name">status</span></td><td><span class="param-type">string</span></td><td class="param-desc"><code style="font-family:var(--mono);font-size:11px">"processing"</code> or <code style="font-family:var(--mono);font-size:11px">"completed"</code>.</td></tr>
                <tr><td><span class="param-name">generation_id</span></td><td><span class="param-type">string</span></td><td class="param-desc">The same generation ID returned from the POST request.</td></tr>
                <tr><td><span class="param-name">image_id</span></td><td><span class="param-type">integer</span></td><td class="param-desc">WordPress media library ID of the saved video (only when completed).</td></tr>
                <tr><td><span class="param-name">url</span></td><td><span class="param-type">string</span></td><td class="param-desc">Full URL to the generated video file (only when completed).</td></tr>
                <tr><td><span class="param-name">message</span></td><td><span class="param-type">string</span></td><td class="param-desc">Human-readable status message.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Flow Diagram -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:20px 24px;margin-top:20px;">
        <h3 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:14px;">📊 Generation Flow</h3>
        <div style="font-family:var(--mono);font-size:12px;color:var(--text-dim);line-height:2;">
            <div><span style="color:#6366f1;">1.</span> POST <span style="color:#a5b4fc;">/generate/character/video</span> → Replicate (instant)</div>
            <div style="padding-left:20px;color:var(--text-muted);">├─ Returns <code style="font-family:var(--mono);font-size:11px">generation_id</code> immediately</div>
            <div style="padding-left:20px;color:var(--text-muted);">└─ Queues <code style="font-family:var(--mono);font-size:11px">retrieve-video-generation</code> cron job</div>
            <div><span style="color:#6366f1;">2.</span> GET <span style="color:#a5b4fc;">/retrieve/generation/{id}</span> ← poll every 10s</div>
            <div style="padding-left:20px;color:var(--text-muted);">├─ processing → <code style="font-family:var(--mono);font-size:11px">{success: false, status: "processing"}</code></div>
            <div style="padding-left:20px;color:var(--text-muted);">└─ completed → <code style="font-family:var(--mono);font-size:11px">{success: true, url: "..."}</code></div>
            <div><span style="color:#6366f1;">3.</span> Cron polls Replicate every 5s (max 5 min)</div>
            <div><span style="color:#6366f1;">4.</span> On success → downloads video → saves to media library → links to model</div>
        </div>
    </div>

</section>
