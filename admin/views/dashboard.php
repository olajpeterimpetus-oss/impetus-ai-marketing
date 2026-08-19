<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap impetus-ai-wrap">
<div class="impetus-header">
    <h1>⚡ AI Marketing OS <span style="font-size:14px;font-weight:400;color:#888;">v<?php echo IMPETUS_AI_VERSION; ?></span></h1>
    <p style="color:#666;margin-top:4px;"><?php echo get_bloginfo('name'); ?> – Impetus Weboldalak</p>
</div>

<?php
global $wpdb;
$posts_table     = $wpdb->prefix . 'impetus_posts';
$campaigns_table = $wpdb->prefix . 'impetus_campaigns';
$total_posts     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$posts_table}");
$published       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$posts_table} WHERE status='published'");
$campaigns       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}");
$api_ok          = ! empty( get_option('impetus_ai_anthropic_key') );
?>

<div class="impetus-stats">
    <div class="impetus-stat-card">
        <div class="stat-number"><?php echo $total_posts; ?></div>
        <div class="stat-label">Osszes poszt</div>
    </div>
    <div class="impetus-stat-card">
        <div class="stat-number"><?php echo $published; ?></div>
        <div class="stat-label">Publikalva</div>
    </div>
    <div class="impetus-stat-card">
        <div class="stat-number"><?php echo $campaigns; ?></div>
        <div class="stat-label">Kampany</div>
    </div>
    <div class="impetus-stat-card">
        <div class="stat-number" style="color:<?php echo $api_ok ? '#1a5c1a' : '#8b2020'; ?>">
            <?php echo $api_ok ? '✓' : '✗'; ?>
        </div>
        <div class="stat-label">API Status</div>
    </div>
</div>

<?php if ( ! $api_ok ) : ?>
<div class="impetus-notice impetus-notice-error">
    ⚠️ Az Anthropic API kulcs nincs beallitva!
    <a href="<?php echo admin_url('admin.php?page=impetus-ai-settings'); ?>">Beallitasok →</a>
</div>
<?php endif; ?>

<div class="impetus-quick-actions">
    <h2>Gyors muvelet</h2>
    <div class="impetus-actions-grid">
        <a href="<?php echo admin_url('admin.php?page=impetus-ai-generate'); ?>" class="impetus-action-card">
            <div class="action-icon">✦</div>
            <div class="action-title">Poszt generalas</div>
            <div class="action-desc">Uj social media poszt AI-val</div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=impetus-ai-campaigns'); ?>" class="impetus-action-card">
            <div class="action-icon">🚀</div>
            <div class="action-title">Uj kampany</div>
            <div class="action-desc">AI tervez optimalis posztsorozatot</div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=impetus-ai-calendar'); ?>" class="impetus-action-card">
            <div class="action-icon">📅</div>
            <div class="action-title">Naptar</div>
            <div class="action-desc">Utemezett posztok attekintese</div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=impetus-ai-settings'); ?>" class="impetus-action-card">
            <div class="action-icon">⚙️</div>
            <div class="action-title">Beallitasok</div>
            <div class="action-desc">API kulcsok es brand profil</div>
        </a>
    </div>
</div>

<?php
$recent = Impetus_AI_Database::get_posts( array() );
$recent = array_slice( $recent, 0, 5 );
if ( ! empty($recent) ) : ?>
<div class="impetus-card" style="margin-top:24px;">
    <h2>Legutobb letrehozva</h2>
    <table class="impetus-table">
        <thead><tr><th>Platform</th><th>Tema</th><th>Statusz</th><th>Letrehozva</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $p) : ?>
        <tr>
            <td><span class="platform-badge platform-<?php echo esc_attr($p->platform); ?>"><?php echo strtoupper(substr($p->platform,0,2)); ?></span></td>
            <td><?php echo esc_html($p->topic ?: '-'); ?></td>
            <td><span class="status-badge status-<?php echo esc_attr($p->status); ?>"><?php echo esc_html($p->status); ?></span></td>
            <td style="font-size:12px;color:#888;"><?php echo substr($p->created_at,0,16); ?></td>
            <td><a href="<?php echo admin_url('admin.php?page=impetus-ai-posts&view='.$p->id); ?>" class="button button-small">Megnyitas</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
</div>
