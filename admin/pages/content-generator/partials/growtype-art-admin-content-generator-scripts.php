<?php

defined('ABSPATH') || exit;

/**
 * Renders all JavaScript for the content generator page.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Scripts::render([...])
 */
class Growtype_Art_Admin_Content_Generator_Scripts
{
    /**
     * @param array $data {
     *   nonce               – WP nonce
     *   js_all_providers    – JSON-encoded providers map
     *   characters_json     – JSON-encoded character list
     *   first_type          – initial content type
     *   reuse_prompt        – pre-filled prompt
     *   reuse_provider      – pre-filled provider
     *   reuse_model         – pre-filled model
     *   reuse_character_id  – pre-filled character ID
     * }
     */
    public static function render(array $data): void
    {
        extract($data, EXTR_SKIP);

        ?>
        <script>
        (function ($) {
            var nonce         = '<?php echo esc_js($nonce); ?>';
            var ajaxUrl       = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            var allProviders  = <?php echo $js_all_providers; ?>;
            var gcCharacters  = <?php echo $characters_json; ?>;
            var currentType   = '<?php echo esc_js($first_type); ?>';

            // ── Helpers ──────────────────────────────────────────────────────
            function getProvidersForType(type) {
                return allProviders[type] || {};
            }

            function updateModelPrice() {
                var provider = $('#gc-provider').val();
                var model    = $('#gc-model').val();
                var provs    = getProvidersForType(currentType);
                var meta     = provs[provider] && provs[provider].models
                    ? provs[provider].models[model]
                    : null;
                var cost     = meta && typeof meta === 'object' ? meta.cost_usd : null;
                var costLabel = meta && typeof meta === 'object' ? meta.cost_label : null;
                var label    = '<?php echo esc_js(__('Price unavailable', 'growtype-art')); ?>';

                if (costLabel) {
                    label = costLabel;
                } else if (cost !== null && cost !== '' && !isNaN(Number(cost))) {
                    cost = Number(cost);
                    if (cost === 0) {
                        label = '<?php echo esc_js(__('Free', 'growtype-art')); ?>';
                    } else {
                        var formatted = cost.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
                        label = '$' + formatted + ' <?php echo esc_js(__('/ image', 'growtype-art')); ?>';
                    }
                }

                $('#gc-model-price-value').text(label);
            }

            function updateCustomSizeVisibility() {
                $('#gc-custom-size-wrap').css(
                    'display',
                    $('#gc-image-size').val() === 'custom' ? 'grid' : 'none'
                );
            }

            function repopulateProviders(type) {
                var provs  = getProvidersForType(type);
                var keys   = Object.keys(provs);
                var $pSel  = $('#gc-provider').empty();
                var $row   = $('.gc-form-row');
                var $none  = $('#gc-no-providers');

                $('#gc-default-prompt-wrap').toggle(type === 'image');
                $('#gc-image-size-wrap').toggle(type === 'image');

                if (!keys.length) {
                    $row.hide();
                    $none.show();
                    return;
                }

                $none.hide();
                $row.show();

                $.each(provs, function (key, data) {
                    $pSel.append('<option value="' + key + '">' + data.label + '</option>');
                });

                repopulateModels(keys[0]);
            }

            function repopulateModels(provider) {
                var provs  = getProvidersForType(currentType);
                var models = (provs[provider] && provs[provider].models) ? provs[provider].models : {};
                var $sel   = $('#gc-model').empty();
                var keys   = Object.keys(models);

                console.log('repopulateModels', {provider: provider, keys: keys});

                $.each(models, function (key, val) {
                    var label = (typeof val === 'object' && val.label) ? val.label : val;
                    var ref   = (typeof val === 'object' && val.ref) ? ' 🖼️' : '';
                    $sel.append('<option value="' + key + '">' + label + ref + '</option>');
                });

                // First option is auto-selected by browser — no manual reset needed
                updateModelPrice();
            }

            // ── Type toggle ──────────────────────────────────────────────────
            $('#gc-type-toggle .gc-type-btn').on('click', function () {
                var type = $(this).data('type');
                currentType = type;
                $(this).addClass('active').siblings().removeClass('active');
                repopulateProviders(type);
            });

            // ── Provider change ──────────────────────────────────────────────
            $('#gc-provider').on('change', function () {
                repopulateModels($(this).val());
            });

            // ── Default prompt presets ───────────────────────────────────────
            $('#gc-default-prompt').on('change', function () {
                var prompt = $(this).find('option:selected').data('prompt') || '';
                if (!prompt) return;
                $('#gc-prompt').val(prompt).trigger('input').focus();
            });

            // ── Reference image ───────────────────────────────────────────────
            (function () {
                var $urlInput = $('#gc-reference-image-url');
                var $preview  = $('#gc-ref-preview');
                var $thumb    = $('#gc-ref-thumb');
                var $lightbox = $('#gc-ref-lightbox');
                var $label    = $('#gc-ref-label');

                function applyUrl(url) {
                    url = (url || '').trim();
                    $urlInput.val(url);
                    if (url) {
                        $thumb.css('opacity', '').attr('src', url);
                        $lightbox.attr('href', url);
                        try {
                            var parts = url.split('/');
                            $label.text(decodeURIComponent(parts[parts.length - 1] || url));
                        } catch(e) { $label.text(url); }
                        $preview.slideDown(150);
                    } else {
                        $preview.slideUp(150);
                        $thumb.attr('src', '');
                        $lightbox.attr('href', '');
                    }
                }

                $urlInput.on('input change', function () { applyUrl($(this).val()); });
                $('#gc-ref-remove').on('click', function () { applyUrl(''); });
                $('#gc-ref-browse').on('click', function () {
                    if (typeof wp === 'undefined' || !wp.media) {
                        var url = prompt('Paste image URL:');
                        if (url) applyUrl(url);
                        return;
                    }
                    var frame = wp.media({
                        title:    'Select Reference Image',
                        button:   { text: 'Use this image' },
                        multiple: false,
                        library:  { type: 'image' }
                    });
                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        applyUrl(att.url);
                    });
                    frame.open();
                });
            }());

            // ── Character autocomplete ────────────────────────────────────────
            (function () {
                var $search   = $('#gc-character-search');
                var $hidden   = $('#gc-character');
                var $dropdown = $('#gc-character-dropdown');
                var focused   = -1;
                var searchTimer = null;
                var searchRequestId = 0;

                function renderItems(items) {
                    $dropdown.empty();
                    if (!items.length) { $dropdown.hide(); return; }
                    items.forEach(function (c, i) {
                        $('<div class="gc-ac-item">')
                            .text('ID: ' + c.id + ' \u2014 ' + c.label)
                            .attr('data-id', c.id)
                            .toggleClass('active', i === focused)
                            .on('mousedown', function (e) { e.preventDefault(); selectChar(c); })
                            .appendTo($dropdown);
                    });
                    $dropdown.show();
                }

                function filter(q) {
                    focused = -1;
                    if (!q) { $hidden.val(0); $dropdown.hide(); return; }
                    var ql = q.toLowerCase();
                    var matches = gcCharacters.filter(function (c) {
                        return String(c.id).indexOf(ql) !== -1
                            || c.label.toLowerCase().indexOf(ql) !== -1
                            || (c.slug && c.slug.toLowerCase().indexOf(ql) !== -1);
                    }).slice(0, 20);
                    renderItems(matches);

                    if (matches.length || q.length < 2) {
                        return;
                    }

                    clearTimeout(searchTimer);
                    var requestId = ++searchRequestId;

                    searchTimer = setTimeout(function () {
                        $.post(ajaxUrl, {
                            action: 'growtype_art_admin_search_characters',
                            _ajax_nonce: nonce,
                            q: q
                        }, function (res) {
                            if (requestId !== searchRequestId || $search.val() !== q) {
                                return;
                            }
                            if (res.success && res.data && Array.isArray(res.data.characters)) {
                                renderItems(res.data.characters);
                            }
                        });
                    }, 180);
                }

                function selectChar(c) {
                    $hidden.val(c.id);
                    $search.val('ID: ' + c.id + ' \u2014 ' + c.label);
                    $dropdown.hide();
                    focused = -1;
                }

                $search.on('input', function () { filter($(this).val()); });
                $search.on('focus', function () { if ($(this).val()) filter($(this).val()); });
                $search.on('keyup', function () { if (!$(this).val()) $hidden.val(0); });
                $search.on('keydown', function (e) {
                    var $items = $dropdown.find('.gc-ac-item');
                    if (e.key === 'ArrowDown') { focused = Math.min(focused + 1, $items.length - 1); $items.removeClass('active').eq(focused).addClass('active'); e.preventDefault(); }
                    else if (e.key === 'ArrowUp') { focused = Math.max(focused - 1, -1); $items.removeClass('active').eq(focused).addClass('active'); e.preventDefault(); }
                    else if (e.key === 'Enter' && focused >= 0) { $items.eq(focused).trigger('mousedown'); e.preventDefault(); }
                    else if (e.key === 'Escape') { $dropdown.hide(); }
                });
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('#gc-character-wrap').length) $dropdown.hide();
                });
            }());

            // ── Generate ─────────────────────────────────────────────────────
            $('#gc-generate-btn').on('click', function () {
                var prompt = $('#gc-prompt').val().trim();
                if (!prompt) { alert('Please enter a prompt.'); return; }

                var provider = $('#gc-provider').val();
                var model    = $('#gc-model').val();

                $('#gc-loading').fadeIn(150);
                $('#gc-generate-btn').prop('disabled', true);

                $.ajax({
                    type: 'POST',
                    url:  ajaxUrl,
                    data: {
                        action:             'growtype_art_admin_generate_content',
                        _ajax_nonce:        nonce,
                        content_type:       currentType,
                        provider:           provider,
                        model:              model,
                        image_size:         $('#gc-image-size').val() || 'default',
                        custom_width:       parseInt($('#gc-custom-width').val(), 10) || 768,
                        custom_height:      parseInt($('#gc-custom-height').val(), 10) || 1024,
                        prompt:             prompt,
                        character_id:       parseInt($('#gc-character').val()) || 0,
                        reference_image_url: $('#gc-reference-image-url').val() || '',
                        compress_image:     $('#gc-opt-compress').is(':checked') ? 1 : 0,
                        remove_background:  $('#gc-opt-bg-remove').is(':checked') ? 1 : 0,
                    },
                    success: function (res) {
                        $('#gc-loading').fadeOut(150);
                        $('#gc-generate-btn').prop('disabled', false);

                        if (!res.success) {
                            showNotice('error', res.data && res.data.message ? res.data.message : 'Something went wrong.');
                            return;
                        }

                        showNotice('success', '✅ Generated successfully!');

                        $.post(ajaxUrl, { action: 'growtype_art_admin_get_recent', _ajax_nonce: nonce }, function (r) {
                            if (r.success && r.data.html) {
                                $('#gc-recent-table').html(r.data.html);
                                $('html, body').animate({ scrollTop: $('#gc-recent-wrap').offset().top - 40 }, 300);
                            }
                        });
                    },
                    error: function () {
                        $('#gc-loading').fadeOut(150);
                        $('#gc-generate-btn').prop('disabled', false);
                        showNotice('error', 'Request failed. Check your connection.');
                    }
                });
            });

            // ── Clear ─────────────────────────────────────────────────────────
            $('#gc-clear-btn').on('click', function () {
                $('#gc-prompt').val('').focus();
                $('#gc-default-prompt').val('');
                $('#gc-image-size').val('default');
                $('#gc-custom-width').val(768);
                $('#gc-custom-height').val(1024);
                updateCustomSizeVisibility();
                $('#gc-reference-image-url').val('').trigger('input');
                try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
            });

            // ── Persist form values across page reloads ───────────────────────
            var STORAGE_KEY = 'gc_generator_form';

            function saveForm() {
                var data = {
                    type:      currentType,
                    provider:  $('#gc-provider').val(),
                    model:     $('#gc-model').val(),
                    imageSize: $('#gc-image-size').val(),
                    customWidth: $('#gc-custom-width').val(),
                    customHeight: $('#gc-custom-height').val(),
                    promptPreset: $('#gc-default-prompt').val(),
                    prompt:    $('#gc-prompt').val(),
                    character: $('#gc-character').val(),
                    charLabel: $('#gc-character-search').val(),
                    refImage:  $('#gc-reference-image-url').val()
                };
                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch(e) {}
            }

            function restoreForm() {
                try {
                    var saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
                    if (!saved) return;

                    if (saved.type && saved.type !== currentType) {
                        var $t = $('#gc-type-toggle .gc-type-btn[data-type="' + saved.type + '"]');
                        if ($t.length) {
                            currentType = saved.type;
                            $t.addClass('active').siblings().removeClass('active');
                            repopulateProviders(saved.type);
                        }
                    }

                    if (saved.provider && $('#gc-provider option[value="' + saved.provider + '"]').length) {
                        $('#gc-provider').val(saved.provider);
                        repopulateModels(saved.provider);
                    }

                    if (saved.model) {
                        if ($('#gc-model option[value="' + saved.model + '"]').length) {
                            $('#gc-model').val(saved.model);
                        }
                    }

                    updateModelPrice();

                    if (saved.promptPreset && $('#gc-default-prompt option[value="' + saved.promptPreset + '"]').length) {
                        $('#gc-default-prompt').val(saved.promptPreset);
                    }

                    if (saved.imageSize && $('#gc-image-size option[value="' + saved.imageSize + '"]').length) {
                        $('#gc-image-size').val(saved.imageSize);
                    }

                    if (saved.customWidth) {
                        $('#gc-custom-width').val(saved.customWidth);
                    }

                    if (saved.customHeight) {
                        $('#gc-custom-height').val(saved.customHeight);
                    }

                    updateCustomSizeVisibility();

                    if (saved.prompt) {
                        $('#gc-prompt').val(saved.prompt);
                    }

                    if (saved.character && saved.character !== '0') {
                        $('#gc-character').val(saved.character);
                        if (saved.charLabel) {
                            $('#gc-character-search').val(saved.charLabel);
                        }
                    }

                    if (saved.refImage) {
                        $('#gc-reference-image-url').val(saved.refImage).trigger('input');
                    }
                } catch(e) {}
            }

            // Save on changes
            $('#gc-prompt').on('input', saveForm);
            $('#gc-default-prompt').on('change', saveForm);
            $('#gc-image-size').on('change', function () { updateCustomSizeVisibility(); saveForm(); });
            $('#gc-custom-width, #gc-custom-height').on('input change', saveForm);
            $('#gc-provider').on('change', function () { repopulateModels($(this).val()); saveForm(); });
            $('#gc-model').on('input change', saveForm);
            $('#gc-model').on('change', updateModelPrice);
            $('#gc-character-search').on('input', saveForm);
            $('#gc-reference-image-url').on('input change', saveForm);
            $('#gc-type-toggle .gc-type-btn').on('click', function () {
                setTimeout(saveForm, 50);
            });

            // ── Ctrl+Enter shortcut ───────────────────────────────────────────
            $('#gc-prompt').on('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') $('#gc-generate-btn').trigger('click');
            });

            // ── Notice helper ─────────────────────────────────────────────────
            function showNotice(type, msg) {
                var bg = type === 'success' ? '#16a34a' : '#dc2626';
                $('.gc-notice-toast').remove();
                $('body').append('<div class="gc-notice-toast" style="position:fixed;top:50px;right:24px;background:' + bg + ';color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 14px rgba(0,0,0,.2);">' + msg + '</div>');
                $('.gc-notice-toast').hide().fadeIn(200).delay(2500).fadeOut(300, function () { $(this).remove(); });
            }

            // ── Restore saved form on normal page load ────────────────────────
            var hasReuse = <?php echo json_encode((bool)($reuse_prompt || $reuse_provider || $reuse_model)); ?>;
            if (!hasReuse) {
                setTimeout(restoreForm, 80);
            }

            // ── Pre-fill from History → Reuse button ────────────────────────────
            var reusePrompt   = <?php echo json_encode($reuse_prompt); ?>;
            var reuseProvider = <?php echo json_encode($reuse_provider); ?>;
            var reuseModel    = <?php echo json_encode($reuse_model); ?>;
            var reuseType     = <?php echo json_encode($first_type); ?>;

            if (reusePrompt || reuseType !== 'text') {
                var $tab = $('#gc-type-toggle .gc-type-btn[data-type="' + reuseType + '"]');
                if ($tab.length) {
                    $tab.trigger('click');
                }

                if (reuseModel && reuseProvider) {
                    var provs = getProvidersForType(currentType);
                    var models = (provs[reuseProvider] && provs[reuseProvider].models) ? provs[reuseProvider].models : {};
                    if (!models[reuseModel]) {
                        var foundProvider = null;
                        $.each(provs, function (pKey, pData) {
                            if (pData.models && pData.models[reuseModel]) {
                                foundProvider = pKey;
                                return false;
                            }
                        });
                        if (foundProvider) {
                            reuseProvider = foundProvider;
                        }
                    }
                }

                if (reuseProvider && $('#gc-provider option[value="' + reuseProvider + '"]').length) {
                    $('#gc-provider').val(reuseProvider);
                    repopulateModels(reuseProvider);
                }

                if (reuseModel) {
                    if ($('#gc-model option[value="' + reuseModel + '"]').length) {
                        $('#gc-model').val(reuseModel);
                    }
                }

                updateModelPrice();

                var reuseCharId = <?php echo (int)$reuse_character_id; ?>;
                if (reuseCharId) {
                    $('#gc-character').val(reuseCharId);
                    var matched = gcCharacters.find(function (c) { return c.id === reuseCharId; });
                    if (matched) {
                        $('#gc-character-search').val('ID: ' + matched.id + ' \u2014 ' + matched.label);
                    }
                }

                if (reusePrompt) {
                    $('#gc-prompt').val(reusePrompt);
                }

                setTimeout(function () {
                    $('html, body').animate({ scrollTop: $('#gc-prompt').offset().top - 60 }, 250);
                    $('#gc-prompt').focus();
                }, 150);
            }

            updateModelPrice();
            updateCustomSizeVisibility();
        }(jQuery));
        </script>
        <?php
    }
}
