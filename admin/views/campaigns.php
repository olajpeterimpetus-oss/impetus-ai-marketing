<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap impetus-ai-wrap">
<div class="impetus-header">
    <h1>🚀 Kampanyok</h1>
    <button class="button button-primary" id="btn-new-campaign">+ Uj kampany</button>
</div>

<!-- New Campaign Form -->
<div class="impetus-card" id="new-campaign-form" style="display:none;">
    <h2>Uj AI Kampany</h2>
    <div class="impetus-two-col">
    <div>
        <div class="impetus-form-group">
            <label>Kampany neve *</label>
            <input type="text" id="camp-name" placeholder="pl. Nyari weboldal akcio 2026">
        </div>
        <div class="impetus-form-group">
            <label>Kampany celja *</label>
            <textarea id="camp-goal" rows="3" placeholder="pl. Uj ugyfelek szerzese juliusban, 20% kedvezmenny weboldalra."></textarea>
        </div>
        <div class="impetus-form-group">
            <label>Platformok</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid #1877f2;border-radius:8px;font-size:13px;">
                    <input type="checkbox" class="camp-platform" value="facebook" checked> <span style="color:#1877f2;font-weight:600;">Facebook</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid #e1306c;border-radius:8px;font-size:13px;">
                    <input type="checkbox" class="camp-platform" value="instagram"> <span style="color:#e1306c;font-weight:600;">Instagram</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid #0077b5;border-radius:8px;font-size:13px;">
                    <input type="checkbox" class="camp-platform" value="linkedin"> <span style="color:#0077b5;font-weight:600;">LinkedIn</span>
                </label>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="impetus-form-group">
                <label>Kezdes *</label>
                <input type="date" id="camp-start">
            </div>
            <div class="impetus-form-group">
                <label>Vege *</label>
                <input type="date" id="camp-end">
            </div>
        </div>
        <button class="button button-primary impetus-btn-full" id="btn-create-campaign">AI Kampany Tervezes</button>
    </div>
    <div>
        <div id="camp-loading" style="display:none;text-align:center;padding:48px;">
            <div class="impetus-spinner"></div>
            <p style="margin-top:16px;color:#666;">AI kampany tervezes...<br><small>Claude megtervezi az optimalis posztsorozatot</small></p>
        </div>
        <div id="camp-result" style="display:none;">
            <div class="impetus-notice impetus-notice-success" id="camp-summary"></div>
            <div id="camp-posts-preview"></div>
            <div style="margin-top:16px;">
                <a id="camp-link" href="#" class="button button-primary">Kampany megnyitasa →</a>
            </div>
        </div>
        <div id="camp-error" style="display:none;" class="impetus-notice impetus-notice-error"></div>
    </div>
    </div>
</div>

<!-- Campaign List -->
<?php $campaigns = Impetus_AI_Database::get_campaigns(); ?>
<?php if ( empty($campaigns) ) : ?>
<div class="impetus-card" style="text-align:center;padding:48px;color:#888;">
    <div style="font-size:36px;margin-bottom:12px;">🚀</div>
    <p>Meg nincs kampany. Hozd letre az elsot!</p>
    <button class="button button-primary" style="margin-top:16px;" onclick="jQuery('#btn-new-campaign').click()">+ Elso kampany</button>
</div>
<?php else : ?>
<div class="impetus-card" style="padding:0;overflow:hidden;">
<table class="impetus-table">
    <thead>
        <tr><th>Nev</th><th>Cel</th><th>Idoszak</th><th>Platformok</th><th>Statusz</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($campaigns as $c) :
        $strategy = json_decode($c->ai_strategy, true) ?? [];
        $post_count = count($strategy['posts'] ?? []);
    ?>
    <tr>
        <td><strong><?php echo esc_html($c->name); ?></strong><br>
            <small style="color:#999;"><?php echo $post_count; ?> poszt</small></td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#666;"><?php echo esc_html($c->goal); ?></td>
        <td style="font-size:12px;white-space:nowrap;"><?php echo $c->start_date; ?><br><?php echo $c->end_date; ?></td>
        <td><?php
            foreach (explode(',', $c->platforms) as $p) {
                $colors = ['facebook'=>'#1877f2','instagram'=>'#e1306c','linkedin'=>'#0077b5'];
                $col = $colors[$p] ?? '#888';
                echo '<span style="color:'.$col.';font-weight:600;font-size:11px;margin-right:4px;">'.strtoupper(substr($p,0,2)).'</span>';
            }
        ?></td>
        <td><span class="status-badge status-<?php echo esc_attr($c->status); ?>"><?php echo $c->status; ?></span></td>
        <td>
            <div class="impetus-actions">
                <a href="<?php echo admin_url('admin.php?page=impetus-ai-posts&campaign='.$c->id); ?>" class="button button-small">Posztok</a>
                <button class="button button-small btn-delete-campaign" data-id="<?php echo $c->id; ?>">Torles</button>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<script>
jQuery(function($) {
    var PLATFORM_COLORS = {facebook:'#1877f2', instagram:'#e1306c', linkedin:'#0077b5'};

    $('#btn-new-campaign').on('click', function() {
        $('#new-campaign-form').slideToggle();
    });

    // Set default dates
    var today = new Date();
    var twoWeeks = new Date(today); twoWeeks.setDate(today.getDate() + 14);
    $('#camp-start').val(today.toISOString().split('T')[0]);
    $('#camp-end').val(twoWeeks.toISOString().split('T')[0]);

    $('#btn-create-campaign').on('click', function() {
        var name  = $('#camp-name').val().trim();
        var goal  = $('#camp-goal').val().trim();
        var start = $('#camp-start').val();
        var end   = $('#camp-end').val();
        var platforms = [];
        $('.camp-platform:checked').each(function() { platforms.push(this.value); });

        if (!name || !goal || !start || !end) { alert('Minden mezo kotelezo!'); return; }
        if (!platforms.length) { alert('Valassz legalabb egy platformot!'); return; }

        $(this).prop('disabled', true);
        $('#camp-loading').show();
        $('#camp-result,#camp-error').hide();

        $.post(impetusAI.ajax_url, {
            action: 'impetus_create_campaign',
            nonce: impetusAI.nonce,
            name: name, goal: goal,
            platforms: platforms,
            start_date: start, end_date: end
        }, function(resp) {
            $('#camp-loading').hide();
            $('#btn-create-campaign').prop('disabled', false);
            if (!resp.success) {
                $('#camp-error').text(resp.data.error || 'Hiba').show();
                return;
            }
            var s = resp.data.strategy;
            $('#camp-summary').text(s.strategy_summary || '');
            var html = '<div style="margin-top:12px;display:flex;flex-direction:column;gap:4px;">';
            (s.posts || []).forEach(function(p) {
                var c = PLATFORM_COLORS[p.platform] || '#888';
                html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f9f8f5;border-radius:6px;border-left:3px solid '+c+';">';
                html += '<span style="color:'+c+';font-weight:600;font-size:11px;min-width:24px;">'+p.platform.substring(0,2).toUpperCase()+'</span>';
                html += '<span style="font-size:13px;flex:1;">'+p.topic+'</span>';
                html += '<span style="font-size:11px;color:#aaa;">'+p.suggested_time+'</span>';
                html += '</div>';
            });
            html += '</div>';
            $('#camp-posts-preview').html(html);
            $('#camp-link').attr('href', '<?php echo admin_url("admin.php?page=impetus-ai-posts&campaign="); ?>' + resp.data.campaign_id);
            $('#camp-result').show();
        }).fail(function() {
            $('#camp-loading').hide();
            $('#btn-create-campaign').prop('disabled', false);
            $('#camp-error').text('Szerver hiba.').show();
        });
    });

    // Delete campaign
    $(document).on('click', '.btn-delete-campaign', function() {
        if (!confirm('Biztosan torlod a kampanyt es az osszes posztjat?')) return;
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(impetusAI.ajax_url, {
            action: 'impetus_delete_campaign',
            nonce: impetusAI.nonce,
            id: id
        }, function(resp) {
            if (resp.success) row.fadeOut();
        });
    });
});
</script>
</div>
