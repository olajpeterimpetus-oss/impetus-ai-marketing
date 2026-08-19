<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap impetus-ai-wrap">
<?php
$view_id     = isset($_GET['view']) ? intval($_GET['view']) : 0;
$campaign_id = isset($_GET['campaign']) ? intval($_GET['campaign']) : 0;

// Single post view
if ( $view_id ) :
    $post = Impetus_AI_Database::get_post($view_id);
    if ( ! $post ) :
        echo '<div class="impetus-notice impetus-notice-error">Poszt nem talalhato.</div>';
    else :
    $image_obj = new Impetus_AI_Image();
?>
<div class="impetus-header">
    <h1><?php echo esc_html($post->platform); ?> poszt</h1>
    <a href="<?php echo admin_url('admin.php?page=impetus-ai-posts'); ?>" class="button">← Vissza</a>
</div>

<div class="impetus-two-col">
<div class="impetus-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">Poszt szovege</h2>
        <span class="status-badge status-<?php echo esc_attr($post->status); ?>"><?php echo $post->status; ?></span>
    </div>
    <?php if ($post->topic) : ?>
    <p style="font-size:13px;color:#999;margin-bottom:10px;">Tema: <?php echo esc_html($post->topic); ?></p>
    <?php endif; ?>
    <?php if ($post->scheduled_at) : ?>
    <p style="font-size:13px;color:#1877f2;margin-bottom:10px;">Utemezve: <?php echo substr($post->scheduled_at,0,16); ?></p>
    <?php endif; ?>
    <div style="font-size:15px;line-height:1.8;white-space:pre-wrap;background:#f9f8f5;padding:14px;border-radius:8px;color:#1a1a18;"><?php echo esc_html($post->caption); ?></div>
    <div style="margin-top:10px;font-size:14px;color:#1D9E75;font-weight:500;"><?php echo esc_html($post->hashtags); ?></div>

    <div class="impetus-actions" style="margin-top:20px;">
        <?php if ($post->status === 'draft' || $post->status === 'planned') : ?>
        <button class="button button-primary" onclick="approvePost(<?php echo $post->id; ?>, this)">✓ Jovahagyas</button>
        <?php endif; ?>
        <?php if ($post->status === 'approved') : ?>
        <button class="button button-primary" onclick="publishPost(<?php echo $post->id; ?>, this)">📢 <?php echo esc_html( ucfirst( $post->platform ) ); ?> publikálás</button>
        <?php endif; ?>
        <button class="button" onclick="copyPostText()">📋 Masolas</button>
        <button class="button" style="color:#8b2020;" onclick="deletePost(<?php echo $post->id; ?>)">Torles</button>
    </div>
    <div id="post-msg" style="display:none;margin-top:10px;font-size:13px;"></div>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0eee6;font-size:12px;color:#aaa;">
        Letrehozva: <?php echo substr($post->created_at,0,16); ?>
        <?php if ($post->published_at) echo ' | Publikalva: '.substr($post->published_at,0,16); ?>
    </div>
</div>
<div>
    <?php if ($post->image_filename) :
        $img_url = $image_obj->get_image_url($post->image_filename); ?>
    <div class="impetus-card" style="padding:12px;">
        <h2 style="margin-bottom:12px;">Generalt kep</h2>
        <img src="<?php echo esc_url($img_url); ?>" style="width:100%;border-radius:8px;display:block;">
        <div style="margin-top:10px;">
            <a href="<?php echo esc_url($img_url); ?>" download class="button button-primary">⬇ Letoltes</a>
        </div>
    </div>
    <?php else : ?>
    <div class="impetus-card" style="text-align:center;padding:48px;color:#bbb;">
        <div style="font-size:36px;margin-bottom:12px;">🖼️</div>
        <p>Ehhez a poszthoz nem keszult kep.</p>
        <a href="<?php echo admin_url('admin.php?page=impetus-ai-generate'); ?>" class="button" style="margin-top:12px;">Uj poszt kepekkel</a>
    </div>
    <?php endif; ?>
</div>
</div>

<script>
function approvePost(id, btn) {
    jQuery.post(impetusAI.ajax_url, {action:'impetus_approve_post', nonce:impetusAI.nonce, id:id}, function(r) {
        if (r.success) {
            jQuery('.status-badge').removeClass().addClass('status-badge status-approved').text('approved');
            jQuery(btn).remove();
            jQuery('#post-msg').css('color','#1a5c1a').text('✓ Jovahagyva!').show();
        }
    });
}
function publishPost(id, btn) {
    if (!confirm('Publikalod ezt a posztot?')) return;
    jQuery(btn).prop('disabled',true).text('Publikaias...');
    jQuery.post(impetusAI.ajax_url, {action:'impetus_publish_post', nonce:impetusAI.nonce, id:id}, function(r) {
        if (r.success) {
            jQuery('.status-badge').removeClass().addClass('status-badge status-published').text('published');
            jQuery(btn).remove();
            jQuery('#post-msg').css('color','#1a5c1a').text('✓ Sikeresen publikalva!').show();
        } else {
            jQuery(btn).prop('disabled',false).text('📢 Publikalas');
            jQuery('#post-msg').css('color','#8b2020').text('Hiba: '+(r.data.error||'Ismeretlen')).show();
        }
    });
}
function copyPostText() {
    var caption = jQuery('.impetus-card div[style*="pre-wrap"]').text();
    var hashtags = jQuery('.impetus-card div[style*="1D9E75"]').text();
    navigator.clipboard.writeText(caption + '\n\n' + hashtags);
    jQuery('#post-msg').css('color','#1a5c1a').text('✓ Vagólapra masolva!').show();
    setTimeout(function(){ jQuery('#post-msg').hide(); }, 2000);
}
function deletePost(id) {
    if (!confirm('Biztosan torlod ezt a posztot?')) return;
    jQuery.post(impetusAI.ajax_url, {action:'impetus_delete_post', nonce:impetusAI.nonce, id:id}, function(r) {
        if (r.success) location.href = '<?php echo admin_url("admin.php?page=impetus-ai-posts"); ?>';
    });
}
</script>
<?php endif; return; endif; ?>

<!-- Posts List -->
<div class="impetus-header">
    <h1>📋 Posztok</h1>
    <a href="<?php echo admin_url('admin.php?page=impetus-ai-generate'); ?>" class="button button-primary">+ Uj poszt</a>
</div>

<?php
$args = array();
if ($campaign_id) $args['campaign_id'] = $campaign_id;
$posts = Impetus_AI_Database::get_posts($args);
?>

<?php if ($campaign_id) : ?>
<div class="impetus-notice" style="background:#e8f0fb;color:#1a3a6c;margin-bottom:16px;">
    Kampany szures aktiv.
    <a href="<?php echo admin_url('admin.php?page=impetus-ai-posts'); ?>">Osszes poszt →</a>
    <?php if ($campaign_id) : ?>
    <button class="button button-small" style="margin-left:10px;" id="btn-gen-all">✦ Osszes tartalom generalasa</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($posts)) : ?>
<div class="impetus-card" style="text-align:center;padding:48px;color:#888;">
    <div style="font-size:36px;margin-bottom:12px;">📋</div>
    <p>Meg nincs poszt.</p>
    <a href="<?php echo admin_url('admin.php?page=impetus-ai-generate'); ?>" class="button button-primary" style="margin-top:12px;">Elso poszt letrehozasa</a>
</div>
<?php else : ?>
<div class="impetus-card" style="padding:0;overflow:hidden;">
<table class="impetus-table">
    <thead>
        <tr><th>Platform</th><th>Tema</th><th>Kampany</th><th>Tartalom</th><th>Kep</th><th>Statusz</th><th>Utemezve</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($posts as $p) :
        $colors = ['facebook'=>'#1877f2','instagram'=>'#e1306c','linkedin'=>'#0077b5'];
        $col = $colors[$p->platform] ?? '#888';
    ?>
    <tr style="cursor:pointer;" onclick="location.href='<?php echo admin_url("admin.php?page=impetus-ai-posts&view=".$p->id); ?>'">
        <td><span style="color:<?php echo $col; ?>;font-weight:700;"><?php echo strtoupper(substr($p->platform,0,2)); ?></span></td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($p->topic ?: '-'); ?></td>
        <td style="font-size:12px;color:#888;"><?php echo esc_html($p->campaign_name ?: '-'); ?></td>
        <td style="text-align:center;"><?php echo $p->caption ? '<span style="color:#1a5c1a;">✓</span>' : '<span style="color:#ccc;">-</span>'; ?></td>
        <td style="text-align:center;"><?php echo $p->image_filename ? '🖼️' : '<span style="color:#ccc;">-</span>'; ?></td>
        <td><span class="status-badge status-<?php echo esc_attr($p->status); ?>"><?php echo $p->status; ?></span></td>
        <td style="font-size:12px;color:#888;white-space:nowrap;"><?php echo $p->scheduled_at ? substr($p->scheduled_at,0,16) : '-'; ?></td>
        <td onclick="event.stopPropagation()">
            <div class="impetus-actions">
                <?php if (!$p->caption) : ?>
                <button class="button button-small btn-gen-post" data-id="<?php echo $p->id; ?>" data-topic="<?php echo esc_attr($p->topic); ?>" data-platform="<?php echo esc_attr($p->platform); ?>">Generalas</button>
                <?php endif; ?>
                <button class="button button-small btn-delete-post" data-id="<?php echo $p->id; ?>">Torles</button>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<div id="gen-progress-bar" style="display:none;" class="impetus-card" style="margin-top:16px;">
    <p id="gen-progress-msg" style="font-size:14px;color:#555;margin-bottom:8px;"></p>
    <div style="background:#f0eee6;border-radius:8px;height:8px;">
        <div id="gen-bar" style="background:#f0a500;height:8px;border-radius:8px;width:0%;transition:width .3s;"></div>
    </div>
</div>

<script>
jQuery(function($) {
    $(document).on('click', '.btn-delete-post', function(e) {
        e.stopPropagation();
        if (!confirm('Biztosan torlod?')) return;
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(impetusAI.ajax_url, {action:'impetus_delete_post', nonce:impetusAI.nonce, id:id}, function(r) {
            if (r.success) row.fadeOut();
        });
    });

    $(document).on('click', '.btn-gen-post', function(e) {
        e.stopPropagation();
        var btn = $(this);
        var topic = btn.data('topic');
        var platform = btn.data('platform');
        var id = btn.data('id');
        btn.prop('disabled', true).text('...');
        $.post(impetusAI.ajax_url, {
            action: 'impetus_generate_post',
            nonce: impetusAI.nonce,
            platform: platform,
            topic: topic,
        }, function(r) {
            if (!r.success) { btn.prop('disabled',false).text('Generalas'); return; }
            $.post(impetusAI.ajax_url, {
                action: 'impetus_save_post',
                nonce: impetusAI.nonce,
                post_id: id,
                platform: platform,
                topic: topic,
                caption: r.data.caption,
                hashtags: r.data.hashtags || '',
            }, function(s) {
                if (!s.success) { btn.prop('disabled',false).text('Generalas'); alert(s.data.error || 'Mentési hiba.'); return; }
                btn.closest('tr').find('td:eq(3)').html('<span style="color:#1a5c1a;">✓</span>');
                btn.closest('td').html('<a href="<?php echo esc_url( admin_url( 'admin.php?page=impetus-ai-posts&view=' ) ); ?>' + id + '" class="button button-small">Megnyitas</a>');
            });
        });
    });

    $('#btn-gen-all').on('click', function() {
        var campaign_id = <?php echo $campaign_id ?: 0; ?>;
        if (!campaign_id) return;
        var btn = $(this);
        btn.prop('disabled', true).text('Generalas...');
        $('#gen-progress-bar').show();
        $('#gen-progress-msg').text('Tartalmak generalasa...');
        $('#gen-bar').css('width', '20%');

        $.post(impetusAI.ajax_url, {
            action: 'impetus_generate_campaign_posts',
            nonce: impetusAI.nonce,
            campaign_id: campaign_id,
        }, function(r) {
            $('#gen-bar').css('width', '100%');
            if (r.success) {
                $('#gen-progress-msg').text('✓ ' + r.data.generated + ' poszt generalva! Az oldal frissitese...');
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                $('#gen-progress-msg').text('Hiba: ' + (r.data.error || 'Ismeretlen'));
                btn.prop('disabled', false).text('✦ Osszes tartalom generalasa');
            }
        });
    });
});
</script>
</div>
