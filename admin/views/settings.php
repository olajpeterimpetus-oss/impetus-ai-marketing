<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap impetus-ai-wrap">
<div class="impetus-header">
    <h1>⚙️ Beallitasok</h1>
</div>

<?php if ( isset($_GET['saved']) ) : ?>
<div class="impetus-notice impetus-notice-success">✓ Beallitasok elmentve!</div>
<?php endif; ?>

<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
<?php wp_nonce_field('impetus_save_settings'); ?>
<input type="hidden" name="action" value="impetus_save_settings">

<div class="impetus-two-col">
<div>

<div class="impetus-card">
    <h2>API Kulcsok</h2>

    <div class="impetus-form-group">
        <label>Anthropic API Kulcs (Claude) *</label>
        <input type="password" name="impetus_ai_anthropic_key"
            value="<?php echo esc_attr(get_option('impetus_ai_anthropic_key','')); ?>"
            placeholder="sk-ant-...">
        <p class="description">Szerezd meg: <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></p>
    </div>

    <div class="impetus-form-group">
        <label>fal.ai API Kulcs (kepgeneralas)</label>
        <input type="password" name="impetus_ai_fal_key"
            value="<?php echo esc_attr(get_option('impetus_ai_fal_key','')); ?>"
            placeholder="fal-...">
        <p class="description">Szerezd meg: <a href="https://fal.ai/dashboard/keys" target="_blank">fal.ai/dashboard/keys</a></p>
    </div>
</div>

<div class="impetus-card">
    <h2>Meta API (Facebook / Instagram)</h2>

    <div class="impetus-form-group">
        <label>Facebook Page ID</label>
        <input type="text" name="impetus_ai_fb_page_id"
            value="<?php echo esc_attr(get_option('impetus_ai_fb_page_id','')); ?>"
            placeholder="pl. 123456789012345">
    </div>

    <div class="impetus-form-group">
        <label>Facebook Page Access Token</label>
        <input type="password" name="impetus_ai_fb_token"
            value="<?php echo esc_attr(get_option('impetus_ai_fb_token','')); ?>"
            placeholder="EAAxxxxxx...">
        <p class="description">Token generaias: <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></p>
    </div>

    <div class="impetus-form-group">
        <label>Instagram Business Account ID (opcionalis)</label>
        <input type="text" name="impetus_ai_ig_id"
            value="<?php echo esc_attr(get_option('impetus_ai_ig_id','')); ?>"
            placeholder="pl. 17841400000000000">
    </div>
</div>

<div class="impetus-card">
    <h2>LinkedIn API</h2>
    <div class="impetus-form-group">
        <label>LinkedIn Access Token</label>
        <input type="password" name="impetus_ai_linkedin_token" value="<?php echo esc_attr(get_option('impetus_ai_linkedin_token','')); ?>" placeholder="Bearer token">
    </div>
    <div class="impetus-form-group">
        <label>LinkedIn Author URN</label>
        <input type="text" name="impetus_ai_linkedin_author" value="<?php echo esc_attr(get_option('impetus_ai_linkedin_author','')); ?>" placeholder="urn:li:organization:123456789">
        <p class="description">Szervezeti oldal eseten az organization URN, szemelyes profilnal a megfelelo author URN szukseges.</p>
    </div>
</div>

</div>
<div>

<div class="impetus-card">
    <h2>Brand Profil</h2>

    <div style="margin-bottom:16px;">
        <button type="button" class="button button-secondary" id="btn-analyze-site" style="width:100%;padding:8px;">
            🔍 Weboldal automatikus elemzese
        </button>
        <p class="description" style="margin-top:6px;">Claude elemzi a weboldalad es kitolti az alabbi mezokat automatikusan.</p>
        <div id="analyze-status" style="display:none;margin-top:8px;font-size:13px;"></div>
    </div>

    <div class="impetus-form-group">
        <label>Iparag</label>
        <input type="text" name="impetus_ai_industry" id="f-industry"
            value="<?php echo esc_attr(get_option('impetus_ai_industry','')); ?>"
            placeholder="pl. webfejlesztes, fodraszat, ugyvedi iroda">
    </div>

    <div class="impetus-form-group">
        <label>Hangnem</label>
        <select name="impetus_ai_tone" id="f-tone">
            <?php foreach (['friendly','professional','casual','formal','enthusiastic'] as $t) : ?>
            <option value="<?php echo $t; ?>" <?php selected(get_option('impetus_ai_tone','friendly'), $t); ?>><?php echo $t; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="impetus-form-group">
        <label>Celcsoport</label>
        <input type="text" name="impetus_ai_target_audience" id="f-audience"
            value="<?php echo esc_attr(get_option('impetus_ai_target_audience','')); ?>"
            placeholder="pl. helyi vallalkozok, 30-50 eves noek">
    </div>

    <div class="impetus-form-group">
        <label>Brand megjegyzesek (AI szamara)</label>
        <textarea name="impetus_ai_brand_notes" id="f-notes" rows="4"
            placeholder="pl. Mindig emeljuk ki a 10 eves tapasztalatot. Ne hasznaljunk angol szavakat."><?php echo esc_textarea(get_option('impetus_ai_brand_notes','')); ?></textarea>
    </div>

    <div class="impetus-form-group">
        <label>CTA gomb szovege</label>
        <input type="text" name="impetus_ai_cta_text"
            value="<?php echo esc_attr(get_option('impetus_ai_cta_text','Keress minket!')); ?>"
            placeholder="pl. Keress minket! / Foglalj idopontot!">
    </div>

    <div class="impetus-form-group">
        <label>Brand fo szin</label>
        <input type="color" name="impetus_ai_primary_color"
            value="<?php echo esc_attr(get_option('impetus_ai_primary_color','#f0a500')); ?>">
    </div>
</div>

</div>
</div>

<div style="margin-top:16px;">
    <button type="submit" class="button button-primary" style="padding:8px 24px;font-size:15px;">Beallitasok mentese</button>
</div>
</form>

<script>
jQuery(function($) {
    $('#btn-analyze-site').on('click', function() {
        var btn = $(this);
        var status = $('#analyze-status');
        btn.prop('disabled', true).text('Elemzes folyamatban...');
        status.show().css('color','#666').text('Weboldal tartalom elemzese Claude-dal...');

        $.post(impetusAI.ajax_url, {
            action: 'impetus_analyze_site',
            nonce: impetusAI.nonce,
        }, function(resp) {
            btn.prop('disabled', false).text('🔍 Weboldal automatikus elemzese');
            if (!resp.success) {
                status.css('color','#8b2020').text('Hiba: ' + (resp.data.error || 'Ismeretlen hiba'));
                return;
            }
            var d = resp.data;
            if (d.industry)        $('#f-industry').val(d.industry);
            if (d.tone)            $('#f-tone').val(d.tone);
            if (d.target_audience) $('#f-audience').val(d.target_audience);
            if (d.brand_notes)     $('#f-notes').val(d.brand_notes);
            status.css('color','#1a5c1a').text('✓ Elemzes kesz! Mentsd el a beallitasokat.');
        }).fail(function() {
            btn.prop('disabled', false).text('🔍 Weboldal automatikus elemzese');
            status.css('color','#8b2020').text('Szerver hiba.');
        });
    });
});
</script>
</div>
