<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap impetus-ai-wrap">
<div class="impetus-header">
    <h1>✦ Poszt Generalas</h1>
</div>

<div class="impetus-two-col">
<div class="impetus-card">
    <h2>Poszt adatok</h2>

    <div class="impetus-form-group">
        <label>Platform</label>
        <div class="platform-select">
            <label class="platform-option active" data-platform="facebook">
                <input type="radio" name="platform" value="facebook" checked> Facebook
            </label>
            <label class="platform-option" data-platform="instagram">
                <input type="radio" name="platform" value="instagram"> Instagram
            </label>
            <label class="platform-option" data-platform="linkedin">
                <input type="radio" name="platform" value="linkedin"> LinkedIn
            </label>
        </div>
    </div>

    <div class="impetus-form-group">
        <label for="topic">Tema / esemeny *</label>
        <input type="text" id="topic" placeholder="pl. Nyari akcio, Uj szolgaltatas, Ugyfel siker">
    </div>

    <div class="impetus-form-group">
        <label for="extra">Extra instrukció (opcionalis)</label>
        <input type="text" id="extra" placeholder="pl. Emeld ki az arat. Kérj véleményt.">
    </div>

    <div class="impetus-form-group">
        <label>Kepgeneralas</label>
        <div class="impetus-toggle-row">
            <label class="impetus-toggle">
                <input type="checkbox" id="with_image">
                <span class="toggle-slider"></span>
            </label>
            <span>Kepet is generaljon</span>
        </div>
        <div id="image-provider-wrap" style="display:none;margin-top:12px;">
            <div class="provider-options">
                <label class="provider-option">
                    <input type="radio" name="image_provider" value="ideogram" checked>
                    <span><strong>Ideogram V4</strong><br><small>Magyar szoveg a kepbe renderelve ~$0.04</small></span>
                </label>
                <label class="provider-option">
                    <input type="radio" name="image_provider" value="flux">
                    <span><strong>FLUX Pro</strong><br><small>Fotorealisztikus hatter ~$0.005</small></span>
                </label>
            </div>
        </div>
    </div>

    <button class="button button-primary impetus-btn-full" id="btn-generate">✦ Generalas</button>
</div>

<div>
    <div class="impetus-card" id="result-placeholder" style="text-align:center;padding:48px;color:#bbb;">
        <div style="font-size:36px;margin-bottom:12px;">✦</div>
        <p>Toltsd ki az adatokat es kattints a Generalas gombra</p>
    </div>

    <div class="impetus-card" id="result-loading" style="display:none;text-align:center;padding:48px;">
        <div class="impetus-spinner"></div>
        <p id="loading-msg" style="margin-top:16px;color:#666;">Generalas folyamatban...</p>
    </div>

    <div class="impetus-card" id="result-box" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="margin:0;" id="result-platform-label">Eredmeny</h2>
            <span class="status-badge status-draft">draft</span>
        </div>

        <div id="result-caption" style="font-size:14px;line-height:1.8;white-space:pre-wrap;background:#f9f8f5;padding:14px;border-radius:8px;color:#1a1a18;"></div>
        <div id="result-hashtags" style="margin-top:10px;font-size:13px;color:#1D9E75;font-weight:500;"></div>

        <div id="image-loading" style="display:none;margin-top:16px;text-align:center;padding:24px;background:#f9f8f5;border-radius:8px;">
            <div class="impetus-spinner"></div>
            <p style="margin-top:12px;color:#666;font-size:13px;">Kep generalas... (20-40 mp)</p>
        </div>

        <div id="image-result" style="display:none;margin-top:16px;">
            <img id="generated-image" src="" style="width:100%;border-radius:8px;display:block;">
            <div style="margin-top:8px;">
                <a id="image-download" href="#" download class="button button-small">⬇ Letoltes</a>
            </div>
        </div>

        <div id="image-error" style="display:none;margin-top:12px;padding:10px;background:#fce8e8;border-radius:8px;font-size:13px;color:#8b2020;"></div>

        <div class="impetus-actions" style="margin-top:20px;">
            <button class="button button-primary" id="btn-save">💾 Mentes</button>
            <button class="button" id="btn-regenerate">↻ Ujra</button>
            <button class="button" id="btn-copy">📋 Masolas</button>
        </div>

        <div id="save-success" style="display:none;margin-top:10px;font-size:13px;color:#1a5c1a;">
            ✓ Poszt elmentve! <a id="save-link" href="#">Megnyitas →</a>
        </div>
    </div>

    <div class="impetus-card" id="result-error" style="display:none;background:#fce8e8;">
        <p style="color:#8b2020;font-size:14px;" id="error-msg"></p>
    </div>
</div>
</div>

<script>
var lastResult = null;
var lastImageData = null;

jQuery(function($) {
    // Platform select
    $('.platform-option').on('click', function() {
        $('.platform-option').removeClass('active');
        $(this).addClass('active');
    });

    // Image toggle
    $('#with_image').on('change', function() {
        $('#image-provider-wrap').toggle(this.checked);
    });

    // Generate
    $('#btn-generate, #btn-regenerate').on('click', function() {
        var topic = $('#topic').val().trim();
        if (!topic) { alert('Add meg a posztot temat!'); return; }

        var platform = $('[name="platform"]:checked').val();
        var extra = $('#extra').val();
        var withImage = $('#with_image').is(':checked');

        show('result-loading');
        $('#loading-msg').text('Szoveg generalas...');
        $('#btn-generate').prop('disabled', true);
        lastResult = null;
        lastImageData = null;
        $('#save-success').hide();
        $('#image-result').hide();
        $('#image-error').hide();

        $.post(impetusAI.ajax_url, {
            action: 'impetus_generate_post',
            nonce: impetusAI.nonce,
            platform: platform,
            topic: topic,
            extra: extra
        }, function(resp) {
            if (!resp.success) {
                $('#error-msg').text(resp.data.error || 'Ismeretlen hiba.');
                show('result-error');
                return;
            }
            lastResult = resp.data;
            lastResult.platform = platform;
            lastResult.topic = topic;
            lastResult.with_image = withImage;
            lastResult.image_provider = $('[name="image_provider"]:checked').val();

            $('#result-caption').text(resp.data.caption);
            $('#result-hashtags').text(resp.data.hashtags);
            $('#result-platform-label').text(platform.charAt(0).toUpperCase() + platform.slice(1) + ' poszt');
            show('result-box');

            // Generate image if requested
            if (withImage && resp.data.image_prompt) {
                generateImage(resp.data.image_prompt, topic, $('[name="image_provider"]:checked').val());
            }
        }).fail(function() {
            $('#error-msg').text('Szerver hiba. Probald ujra.');
            show('result-error');
        }).always(function() {
            $('#btn-generate').prop('disabled', false);
        });
    });

    function generateImage(prompt, topic, provider) {
        $('#image-loading').show();
        $.post(impetusAI.ajax_url, {
            action: 'impetus_generate_image',
            nonce: impetusAI.nonce,
            image_prompt: prompt,
            topic: topic,
            provider: provider
        }, function(resp) {
            $('#image-loading').hide();
            if (!resp.success) {
                $('#image-error').text(resp.data.error || 'Kep generalas sikertelen.').show();
                return;
            }
            lastImageData = resp.data;
            $('#generated-image').attr('src', resp.data.url);
            $('#image-download').attr('href', resp.data.url);
            $('#image-result').show();
        }).fail(function() {
            $('#image-loading').hide();
            $('#image-error').text('Kep generalas szerver hiba.').show();
        });
    }

    // Save
    $('#btn-save').on('click', function() {
        if (!lastResult) return;
        var btn = $(this);
        btn.prop('disabled', true).text('Mentes...');

        $.post(impetusAI.ajax_url, {
            action: 'impetus_save_post',
            nonce: impetusAI.nonce,
            platform: lastResult.platform,
            topic: lastResult.topic,
            caption: lastResult.caption,
            hashtags: lastResult.hashtags,
            image_url: lastImageData ? lastImageData.url : '',
            image_filename: lastImageData ? lastImageData.filename : '',
        }, function(resp) {
            btn.prop('disabled', false).text('💾 Mentes');
            if (resp.success) {
                $('#save-link').attr('href', '<?php echo admin_url("admin.php?page=impetus-ai-posts&view="); ?>' + resp.data.id);
                $('#save-success').show();
            }
        });
    });

    // Copy
    $('#btn-copy').on('click', function() {
        if (!lastResult) return;
        var text = lastResult.caption + '\n\n' + lastResult.hashtags;
        navigator.clipboard.writeText(text);
        $(this).text('✓ Masolva!');
        setTimeout(function() { $('#btn-copy').text('📋 Masolas'); }, 2000);
    });

    function show(id) {
        ['result-placeholder','result-loading','result-box','result-error'].forEach(function(i) {
            $('#' + i).toggle(i === id);
        });
    }
});
</script>
