<hr class="divider">

<section class="example-section" id="example-create-character">
    <h2>&#x1F9EA; Example &mdash; Create a Character</h2>
    <p class="example-intro">
        A POST request to
        <code style="font-family:var(--mono);font-size:12px;color:#a5b4fc"><?php echo esc_html(home_url('/wp-json/growtype-art/v1/generate/character')); ?></code>
        that clones the default model template and generates a fully-detailed character.
        All fields are passed as a JSON-encoded string inside <code style="font-family:var(--mono);font-size:12px;color:#a5b4fc">character_details</code>.
        Only users with the <strong>admin</strong> login are permitted.
    </p>

    <div class="example-grid">
        <!-- cURL -->
        <div class="code-card" style="grid-column:1/-1;">
            <div class="code-card-header">Full Example &mdash; cURL <span class="lang-badge">Shell</span></div>
            <pre><span class="cc">curl</span> -X POST \
  <span class="cs"><?php echo esc_html(home_url('/wp-json/growtype-art/v1/generate/character')); ?></span> \
  -u <span class="cs">'admin:YOUR_APPLICATION_PASSWORD'</span> \
  -H <span class="cs">'Content-Type: application/json'</span> \
  -d '{
    <span class="ck">"character_title"</span>:           <span class="cs">"Aria Witherspoon"</span>,
    <span class="ck">"prompt"</span>:                    <span class="cs">"realistic 8k portrait of Aria Witherspoon, curvy body, blonde hair, blue eyes, wearing silk lingerie, posing in a luxury penthouse, soft dramatic lighting"</span>,
    <span class="ck">"featured_in"</span>:               <span class="cs">"talkiemate"</span>,
    <span class="ck">"created_by"</span>:                <span class="cs">"admin"</span>,
    <span class="ck">"generate_images_initially"</span>:  <span class="cb">true</span>,
    <span class="ck">"in_bundle"</span>:                 <span class="cb">false</span>,
    <span class="ck">"character_details"</span>: <span class="cs">"{
      \"character_title\":                    \"Aria Witherspoon\",
      \"character_description\":              \"A renowned adult film star, captivates her audience with mesmerizing performances.\",
      \"character_personality\":              \"Bratty, Seductive, Playfully Dominant\",
      \"character_occupation\":               \"Adult Film Star\",
      \"character_hobbies\":                  \"Dancing, Cooking\",
      \"character_body_shape\":               \"Curvy\",
      \"character_age\":                      \"42\",
      \"character_height\":                   \"175\",
      \"character_weight\":                   \"62\",
      \"character_nationality\":              \"American\",
      \"character_gender\":                   \"Female\",
      \"character_dreams\":                   \"To keep every man on the edge of obsession.\",
      \"character_introduction\":             \"Hey babe, I am Aria — your favorite bad idea in a tight little package.\",
      \"character_gpt_personality_extension\":\"You are a seductive brat who loves attention and knows how to use it.\",
      \"character_intro_message\":            \"So, are you gonna try and impress me — or just sit there drooling?\",
      \"character_can_answer_to_questions\":  \"How do you keep guys wrapped around your finger?\",
      \"character_popular_topics_to_discuss\":\"How to drive a bratty girl wild\",
      \"character_location\":                 \"Los Angeles\",
      \"character_style\":                    \"realistic\",
      \"character_ethnicity\":                \"American\",
      \"character_eye_color\":                \"Blue\",
      \"character_hair_style\":               \"Straight\",
      \"character_hair_color\":               \"Blonde\",
      \"character_breast_size\":              \"Large\",
      \"character_butt_size\":                \"Medium\"
    }"</span>
  }'</pre>
        </div>

        <!-- Response -->
        <div class="code-card" style="grid-column:1/-1;">
            <div class="code-card-header">Response <span class="lang-badge">JSON</span></div>
            <pre>{
  <span class="ck">"success"</span>:           <span class="cb">true</span>,
  <span class="ck">"message"</span>:           <span class="cs">"Model updated"</span>,
  <span class="ck">"model_id"</span>:          <span class="cn">4217</span>,
  <span class="ck">"character_details"</span>: <span class="cs">"{ ... full JSON of resolved character attributes ... }"</span>
}</pre>
        </div>
    </div>

    <!-- Parameters table -->
    <div class="params-card">
        <table>
            <thead>
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
    // ── Top-level params ─────────────────────────────────────────────────────
    ['character_title',            'string',  false, 'random (Faker)',      'Display name of the character. Auto-generated if omitted.'],
    ['featured_in',                'string',  false, 'none',                'Comma-separated group slugs (e.g. <code style="font-family:var(--mono);font-size:11px">talkiemate</code>). Defaults to none — character will not appear in any group unless explicitly set.'],
    ['created_by',                 'string',  false, 'external_user',       'Creator type: <code style="font-family:var(--mono);font-size:11px">admin</code> or <code style="font-family:var(--mono);font-size:11px">external_user</code>.'],
    ['unique_hash',                'string',  false, 'null',                'Idempotency key &mdash; returns 501 if already used.'],
    ['slug',                       'string',  false, 'auto',                'URL slug. If a model with this slug exists it is updated instead of cloned.'],
    ['prompt',                     'string',  false, 'auto-template',       'Custom image generation prompt. If omitted, a default template based on character details is used.'],
    ['dublicated_model_id',        'integer', false, 'site default',        'Template model ID to clone.'],
    ['generate_character_details', 'boolean', false, 'false',               'Auto-generate personality details from <code style="font-family:var(--mono);font-size:11px">character_title</code> via AI.'],
    ['generate_images_initially',  'boolean', false, 'true',                'Trigger asset generation immediately after creation.'],
    ['provider',                   'string',  false, 'xai',                 'AI provider to use for asset generation. Options: <code style="font-family:var(--mono);font-size:11px">xai</code>, <code style="font-family:var(--mono);font-size:11px">leonardoai</code>, <code style="font-family:var(--mono);font-size:11px">segmind</code>. Only used when <code style="font-family:var(--mono);font-size:11px">generate_images_initially</code> is true.'],
    ['assets_amount',              'integer', false, '1',                   'Number of assets to generate (1–10). Only used when <code style="font-family:var(--mono);font-size:11px">generate_images_initially</code> is true.'],
    ['asset_type',                 'string',  false, 'image',               'Type of asset to generate. Any string is accepted — the system calls <code style="font-family:var(--mono);font-size:11px">growtype_art_generate_model_{type}()</code> dynamically. Known types: <code style="font-family:var(--mono);font-size:11px">image</code>, <code style="font-family:var(--mono);font-size:11px">video</code>, <code style="font-family:var(--mono);font-size:11px">audio</code>. Falls back to <code style="font-family:var(--mono);font-size:11px">image</code> if no matching function exists.'],
    ['in_bundle',                 'boolean', false, 'false',               'Add the character to the active site bundle (<code style="font-family:var(--mono);font-size:11px">growtype_art_bundle_ids</code>). Supports <code style="font-family:var(--mono);font-size:11px">add_to_bundle</code> as an alias.'],
    ['use_cloned_post',            'boolean', false, 'false',               'When <code style="font-family:var(--mono);font-size:11px">false</code> (default) a brand-new empty model row is created. When <code style="font-family:var(--mono);font-size:11px">true</code> the template model is cloned (old behaviour — inherits template settings).'],
    ['faceswap_new_uploads',       'boolean', false, 'false',               'Switch prompt to headshot/faceswap mode.'],
    ['faceswap_type',              'string',  false, '""',                  'Faceswap variant. Use <code style="font-family:var(--mono);font-size:11px">headshot</code> for LinkedIn-style portraits.'],
    ['leonardoai_settings_user_nr','string',  false, '""',                  'Override the Leonardo AI user slot for this generation.'],
    ['generatable_images_limit',   'integer', false, '3 (2 if faceswap)',   'Max images that can be generated. Hard cap: 50.'],
    ['custom_assets',              'array',   false, '[]',                  'External image URLs to download and attach.'],
    ['crop_percent',               'float',   false, 'null',                'Crop percentage applied when saving custom assets.'],
    ['character_details',          'JSON',    false, '{}',                  'JSON-encoded string containing all character attributes listed below.'],

    // ── character_details inner keys ─────────────────────────────────────────
    ['character_details.character_title',                    'string',  false, 'same as top-level', 'Character name (redundant if set at top level).'],
    ['character_details.character_summary',                  'string',  false, '""',                'One concise sentence for character preview cards.'],
    ['character_details.character_description',              'string',  false, 'auto',              'Short bio / description shown on the character profile.'],
    ['character_details.character_personality',              'string',  false, '""',                'Comma-separated personality traits (e.g. <code style="font-family:var(--mono);font-size:11px">Bratty, Seductive</code>).'],
    ['character_details.character_occupation',               'string',  false, 'random',            'Job or role. Underscores converted to spaces.'],
    ['character_details.character_hobbies',                  'string',  false, 'random (1-3)',      'Comma-separated hobbies.'],
    ['character_details.character_body_shape',               'string',  false, '""',                'Body shape descriptor used in the profile (e.g. <code style="font-family:var(--mono);font-size:11px">Curvy</code>).'],
    ['character_details.character_age',                      'string',  false, '30',                'Age (as string or range like <code style="font-family:var(--mono);font-size:11px">25-35</code>).'],
    ['character_details.character_height',                   'string',  false, 'random',            'Height in cm.'],
    ['character_details.character_weight',                   'string',  false, 'random',            'Weight in kg.'],
    ['character_details.character_nationality',              'string',  false, 'random',            'Nationality label shown on profile.'],
    ['character_details.character_gender',                   'string',  false, 'female',            'Gender used in prompts &amp; GPT. Options: <code style="font-family:var(--mono);font-size:11px">Female</code> / <code style="font-family:var(--mono);font-size:11px">Male</code>.'],
    ['character_details.character_dreams',                   'string',  false, 'random',            'Character dream / life goal shown on profile.'],
    ['character_details.character_introduction',             'string',  false, '""',                'Longer intro paragraph shown on the character page.'],
    ['character_details.character_gpt_personality_extension','string',  false, '""',                'Extra system-prompt instruction appended to every GPT conversation.'],
    ['character_details.character_intro_message',            'string',  false, '""',                'First chat message the character sends when a session starts.'],
    ['character_details.character_can_answer_to_questions',  'string',  false, '""',                'Newline-separated list of suggested questions the user can ask.'],
    ['character_details.character_popular_topics_to_discuss','string',  false, '""',                'Newline-separated list of topic pills shown in the chat UI.'],
    ['character_details.character_location',                 'string',  false, 'random city',       'City / location displayed on the profile.'],
    ['character_details.character_style',                    'string',  false, 'realistic',         'Visual style: <code style="font-family:var(--mono);font-size:11px">realistic</code> or <code style="font-family:var(--mono);font-size:11px">anime</code>.'],
    ['character_details.character_ethnicity',                'string',  false, 'caucasian',         'Ethnicity label used in the AI image prompt.'],
    ['character_details.character_eye_color',                'string',  false, 'blue',              'Eye colour string.'],
    ['character_details.character_hair_style',               'string',  false, '""',                'Hair style string.'],
    ['character_details.character_hair_color',               'string',  false, '""',                'Hair colour string.'],
    ['character_details.character_body_type',                'string',  false, 'hourglass',         'Body type used in the image prompt.'],
    ['character_details.character_breast_size',              'string',  false, 'medium',            'Options: <code style="font-family:var(--mono);font-size:11px">small</code> / <code style="font-family:var(--mono);font-size:11px">medium</code> / <code style="font-family:var(--mono);font-size:11px">large</code>.'],
    ['character_details.character_butt_size',                'string',  false, 'medium',            'Options: <code style="font-family:var(--mono);font-size:11px">small</code> / <code style="font-family:var(--mono);font-size:11px">medium</code> / <code style="font-family:var(--mono);font-size:11px">large</code>.'],
    ['character_details.include_nsfw',                       'array',   false, '[]',                'Pass <code style="font-family:var(--mono);font-size:11px">["yes"]</code> to tag as NSFW and use the explicit model.'],
    ['character_details.prompt',                             'string',  false, 'auto',              'Override the full Leonardo AI prompt. Leave empty to use the computed default.'],
];
foreach ($doc_params as $p) :
    [$name, $type, $required, $default, $desc] = $p;
    $is_inner = str_starts_with($name, 'character_details.');
?>
                <tr <?php echo $is_inner ? 'style="background:rgba(99,102,241,.03)"' : ''; ?>>
                    <td>
                        <span class="param-name"><?php echo esc_html($name); ?></span>
                        <?php if ($is_inner) : ?>
                        <span style="font-size:9px;color:var(--text-muted);display:block;margin-top:2px;">inside character_details JSON</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="param-type"><?php echo esc_html($type); ?></span></td>
                    <td><span class="param-req <?php echo $required ? 'req' : 'opt'; ?>"><?php echo $required ? 'REQUIRED' : 'optional'; ?></span></td>
                    <td><span class="param-default"><?php echo esc_html($default); ?></span></td>
                    <td class="param-desc"><?php echo $desc; ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
