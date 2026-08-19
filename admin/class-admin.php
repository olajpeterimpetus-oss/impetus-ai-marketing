<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Impetus_AI_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_impetus_save_settings', array( $this, 'save_settings' ) );
        add_action( 'impetus_ai_publish_scheduled_posts', array( 'Impetus_AI_Publisher', 'cron_publish_scheduled' ) );

        // AJAX handlers
        $ajax_actions = array(
            'impetus_generate_post',
            'impetus_save_post',
            'impetus_delete_post',
            'impetus_approve_post',
            'impetus_publish_post',
            'impetus_generate_image',
            'impetus_create_campaign',
            'impetus_delete_campaign',
            'impetus_generate_campaign_posts',
            'impetus_calendar_data',
            'impetus_analyze_site',
            'impetus_update_schedule',
        );

        foreach ( $ajax_actions as $action ) {
            add_action( 'wp_ajax_' . $action, array( $this, $action ) );
        }
    }

    public function add_menu() {
        add_menu_page(
            'AI Marketing',
            'AI Marketing',
            'manage_options',
            'impetus-ai',
            array( $this, 'page_dashboard' ),
            'dashicons-megaphone',
            30
        );

        add_submenu_page( 'impetus-ai', 'Dashboard', 'Dashboard', 'manage_options', 'impetus-ai', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'impetus-ai', 'Poszt Generalas', 'Poszt Generalas', 'manage_options', 'impetus-ai-generate', array( $this, 'page_generate' ) );
        add_submenu_page( 'impetus-ai', 'Kampanyok', 'Kampanyok', 'manage_options', 'impetus-ai-campaigns', array( $this, 'page_campaigns' ) );
        add_submenu_page( 'impetus-ai', 'Naptar', 'Naptar', 'manage_options', 'impetus-ai-calendar', array( $this, 'page_calendar' ) );
        add_submenu_page( 'impetus-ai', 'Posztok', 'Posztok', 'manage_options', 'impetus-ai-posts', array( $this, 'page_posts' ) );
        add_submenu_page( 'impetus-ai', 'Beallitasok', 'Beallitasok', 'manage_options', 'impetus-ai-settings', array( $this, 'page_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'impetus-ai' ) === false ) return;

        wp_enqueue_style( 'impetus-ai-admin', IMPETUS_AI_PLUGIN_URL . 'assets/admin.css', array(), IMPETUS_AI_VERSION );
        wp_enqueue_script( 'impetus-ai-admin', IMPETUS_AI_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), IMPETUS_AI_VERSION, true );
        wp_localize_script( 'impetus-ai-admin', 'impetusAI', array(
            'nonce'    => wp_create_nonce( 'impetus_ai_nonce' ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'site_name' => get_bloginfo('name'),
        ) );
    }

    // =====================
    // PAGE RENDERERS
    // =====================

    public function page_dashboard() {
        include IMPETUS_AI_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function page_generate() {
        include IMPETUS_AI_PLUGIN_DIR . 'admin/views/generate.php';
    }

    public function page_campaigns() {
        include IMPETUS_AI_PLUGIN_DIR . 'admin/views/campaigns.php';
    }

    public function page_calendar() {
        include IMPETUS_AI_PLUGIN_DIR . 'admin/views/calendar.php';
    }

    public function page_posts() {
        include IMPETUS_AI_PLUGIN_DIR . 'admin/views/posts.php';
    }

    public function page_settings() {
        include IMPETUS_AI_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // =====================
    // SETTINGS
    // =====================

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'impetus_save_settings' );

        $options = array(
            'impetus_ai_anthropic_key',
            'impetus_ai_fal_key',
            'impetus_ai_fb_page_id',
            'impetus_ai_fb_token',
            'impetus_ai_ig_id',
            'impetus_ai_linkedin_token',
            'impetus_ai_linkedin_author',
            'impetus_ai_industry',
            'impetus_ai_tone',
            'impetus_ai_target_audience',
            'impetus_ai_brand_notes',
            'impetus_ai_cta_text',
            'impetus_ai_primary_color',
        );

        foreach ( $options as $opt ) {
            if ( ! isset( $_POST[ $opt ] ) ) continue;
            $value = wp_unslash( $_POST[ $opt ] );
            if ( $opt === 'impetus_ai_primary_color' ) {
                $value = sanitize_hex_color( $value );
            } elseif ( in_array( $opt, array( 'impetus_ai_anthropic_key', 'impetus_ai_fal_key', 'impetus_ai_fb_token', 'impetus_ai_linkedin_token' ), true ) ) {
                $value = sanitize_text_field( $value );
            } elseif ( in_array( $opt, array( 'impetus_ai_tone' ), true ) ) {
                $allowed = array( 'friendly', 'professional', 'casual', 'formal', 'enthusiastic' );
                $value = in_array( $value, $allowed, true ) ? $value : 'friendly';
            } else {
                $value = sanitize_textarea_field( $value );
            }
            update_option( $opt, $value );
        }

        wp_redirect( admin_url( 'admin.php?page=impetus-ai-settings&saved=1' ) );
        exit;
    }

    // =====================
    // AJAX HANDLERS
    // =====================

    private function verify_nonce() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'error' => 'Nincs jogosultsagod ehhez a muvelethez.' ), 403 );
        }
        if ( ! check_ajax_referer( 'impetus_ai_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'error' => 'Biztonsagi hiba.' ), 403 );
        }
    }

    public function impetus_generate_post() {
        $this->verify_nonce();
        $platform = sanitize_text_field( $_POST['platform'] ?? 'facebook' );
        $topic    = sanitize_text_field( $_POST['topic'] ?? '' );
        $extra    = sanitize_text_field( $_POST['extra'] ?? '' );

        if ( empty( $topic ) ) {
            wp_send_json_error( array( 'error' => 'A tema mezo kotelezo!' ) );
        }

        $gen    = new Impetus_AI_Generator();
        $result = $gen->generate_post( $platform, $topic, $extra );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result );
        }

        wp_send_json_success( $result );
    }

    public function impetus_generate_image() {
        $this->verify_nonce();
        $image_prompt = sanitize_text_field( $_POST['image_prompt'] ?? '' );
        $topic        = sanitize_text_field( $_POST['topic'] ?? '' );
        $provider     = sanitize_text_field( $_POST['provider'] ?? 'ideogram' );

        $img    = new Impetus_AI_Image();
        $result = $img->generate( $image_prompt ?: $topic, $topic, $provider );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result );
        }

        wp_send_json_success( $result );
    }

    public function impetus_save_post() {
        $this->verify_nonce();
        $platform = sanitize_key( $_POST['platform'] ?? 'facebook' );
        $allowed_platforms = array( 'facebook', 'instagram', 'linkedin' );
        if ( ! in_array( $platform, $allowed_platforms, true ) ) {
            wp_send_json_error( array( 'error' => 'Nem tamogatott platform.' ) );
        }
        $scheduled = sanitize_text_field( $_POST['scheduled_at'] ?? '' );
        if ( $scheduled !== '' && ! $this->valid_datetime( $scheduled ) ) {
            wp_send_json_error( array( 'error' => 'Ervenytelen utemezesi datum.' ) );
        }
        $data = array(
            'campaign_id'    => intval( $_POST['campaign_id'] ?? 0 ) ?: null,
            'platform'       => $platform,
            'topic'          => sanitize_text_field( $_POST['topic'] ?? '' ),
            'caption'        => sanitize_textarea_field( $_POST['caption'] ?? '' ),
            'hashtags'       => sanitize_text_field( $_POST['hashtags'] ?? '' ),
            'image_url'      => esc_url_raw( $_POST['image_url'] ?? '' ),
            'image_filename' => sanitize_file_name( $_POST['image_filename'] ?? '' ),
            'status'         => 'draft',
            'scheduled_at'   => $scheduled !== '' ? $scheduled : null,
        );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( $post_id > 0 ) {
            $existing = Impetus_AI_Database::get_post( $post_id );
            if ( ! $existing ) wp_send_json_error( array( 'error' => 'A poszt nem talalhato.' ) );
            if ( empty( $data['scheduled_at'] ) ) $data['scheduled_at'] = $existing->scheduled_at;
            $data['status'] = $existing->status === 'approved' || $existing->status === 'published' ? $existing->status : 'draft';
            $ok = Impetus_AI_Database::update_post( $post_id, $data );
            if ( false === $ok ) wp_send_json_error( array( 'error' => 'A poszt frissitese sikertelen.' ) );
            wp_send_json_success( array( 'id' => $post_id, 'updated' => true ) );
        }
        $id = Impetus_AI_Database::save_post( $data );
        if ( ! $id ) wp_send_json_error( array( 'error' => 'A poszt mentese sikertelen.' ) );
        wp_send_json_success( array( 'id' => $id ) );
    }

    private function valid_datetime( $value ) {
        $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
        return $dt && $dt->format( 'Y-m-d H:i:s' ) === $value;
    }

    public function impetus_delete_post() {
        $this->verify_nonce();
        $id = intval( $_POST['id'] ?? 0 );

        $post = Impetus_AI_Database::get_post( $id );
        if ( $post && ! empty( $post->image_filename ) ) {
            $img = new Impetus_AI_Image();
            $img->delete_image( $post->image_filename );
        }

        Impetus_AI_Database::delete_post( $id );
        wp_send_json_success();
    }

    public function impetus_approve_post() {
        $this->verify_nonce();
        $id = intval( $_POST['id'] ?? 0 );
        $post = Impetus_AI_Database::get_post( $id );
        if ( ! $post ) wp_send_json_error( array( 'error' => 'Poszt nem talalhato.' ) );
        if ( empty( $post->caption ) ) wp_send_json_error( array( 'error' => 'Ures tartalmu poszt nem hagyhato jová.' ) );
        $ok = Impetus_AI_Database::update_post( $id, array( 'status' => 'approved' ) );
        if ( false === $ok ) wp_send_json_error( array( 'error' => 'Statusz frissitese sikertelen.' ) );
        wp_send_json_success();
    }

    public function impetus_publish_post() {
        $this->verify_nonce();
        $id        = intval( $_POST['id'] ?? 0 );
        $publisher = new Impetus_AI_Publisher();
        $result    = $publisher->publish_post( $id );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result );
        }
        wp_send_json_success( $result );
    }

    public function impetus_create_campaign() {
        $this->verify_nonce();
        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $goal       = sanitize_textarea_field( $_POST['goal'] ?? '' );
        $platforms  = array_map( 'sanitize_text_field', (array)( $_POST['platforms'] ?? array('facebook') ) );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

        if ( empty( $name ) || empty( $goal ) || empty( $start_date ) || empty( $end_date ) ) {
            wp_send_json_error( array( 'error' => 'Minden mezo kotelezo!' ) );
        }
        $allowed_platforms = array( 'facebook', 'instagram', 'linkedin' );
        $platforms = array_values( array_intersect( $platforms, $allowed_platforms ) );
        if ( empty( $platforms ) ) wp_send_json_error( array( 'error' => 'Legalabb egy tamogatott platformot valassz.' ) );
        $start = DateTime::createFromFormat( 'Y-m-d', $start_date );
        $end   = DateTime::createFromFormat( 'Y-m-d', $end_date );
        if ( ! $start || ! $end || $start->format('Y-m-d') !== $start_date || $end->format('Y-m-d') !== $end_date || $end < $start ) {
            wp_send_json_error( array( 'error' => 'Ervenytelen kampanydatumok.' ) );
        }

        $gen      = new Impetus_AI_Generator();
        $strategy = $gen->generate_campaign_strategy( $name, $goal, $platforms, $start_date, $end_date );

        if ( isset( $strategy['error'] ) ) {
            wp_send_json_error( $strategy );
        }

        $campaign_id = Impetus_AI_Database::save_campaign( array(
            'name'        => $name,
            'goal'        => $goal,
            'platforms'   => implode( ',', $platforms ),
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'status'      => 'active',
            'ai_strategy' => json_encode( $strategy ),
        ) );

        // Create post placeholders
        foreach ( $strategy['posts'] ?? array() as $plan ) {
            $base   = strtotime( $start_date );
            $offset = max( 0, min( (int) $start->diff( $end )->days, intval( $plan['day_offset'] ?? 0 ) ) );
            $time   = preg_match('/^\d{2}:\d{2}$/', $plan['suggested_time'] ?? '') ? $plan['suggested_time'] : '10:00';
            $sched  = date( 'Y-m-d', $base + $offset * 86400 ) . ' ' . $time . ':00';

            Impetus_AI_Database::save_post( array(
                'campaign_id'  => $campaign_id,
                'platform'     => in_array( sanitize_key( $plan['platform'] ?? 'facebook' ), $platforms, true ) ? sanitize_key( $plan['platform'] ?? 'facebook' ) : $platforms[0],
                'topic'        => sanitize_text_field( substr( $plan['topic'] ?? '', 0, 200 ) ),
                'caption'      => '',
                'hashtags'     => '',
                'status'       => 'planned',
                'scheduled_at' => $sched,
            ) );
        }

        wp_send_json_success( array( 'campaign_id' => $campaign_id, 'strategy' => $strategy ) );
    }

    public function impetus_delete_campaign() {
        $this->verify_nonce();
        $id = intval( $_POST['id'] ?? 0 );

        // Delete images
        $posts = Impetus_AI_Database::get_posts( array( 'campaign_id' => $id ) );
        $img   = new Impetus_AI_Image();
        foreach ( $posts as $post ) {
            if ( ! empty( $post->image_filename ) ) {
                $img->delete_image( $post->image_filename );
            }
        }

        Impetus_AI_Database::delete_campaign( $id );
        wp_send_json_success();
    }

    public function impetus_generate_campaign_posts() {
        $this->verify_nonce();
        $campaign_id = intval( $_POST['campaign_id'] ?? 0 );
        $posts       = Impetus_AI_Database::get_posts( array( 'campaign_id' => $campaign_id ) );
        $gen         = new Impetus_AI_Generator();
        $generated   = 0;

        foreach ( $posts as $post ) {
            if ( ! empty( $post->caption ) ) continue;
            $result = $gen->generate_post( $post->platform, $post->topic );
            if ( ! isset( $result['error'] ) ) {
                Impetus_AI_Database::update_post( $post->id, array(
                    'caption'  => $result['caption'] ?? '',
                    'hashtags' => $result['hashtags'] ?? '',
                    'status'   => 'draft',
                ) );
                $generated++;
            }
        }

        wp_send_json_success( array( 'generated' => $generated ) );
    }

    public function impetus_calendar_data() {
        $this->verify_nonce();
        $month = sanitize_text_field( $_POST['month'] ?? date('Y-m') );
        $posts = Impetus_AI_Database::get_posts( array( 'month' => $month ) );

        $events = array();
        foreach ( $posts as $p ) {
            $events[] = array(
                'id'            => $p->id,
                'topic'         => $p->topic,
                'platform'      => $p->platform,
                'status'        => $p->status,
                'scheduled_at'  => $p->scheduled_at,
                'campaign_name' => $p->campaign_name ?? '',
                'has_caption'   => ! empty( $p->caption ),
                'has_image'     => ! empty( $p->image_filename ),
            );
        }

        wp_send_json_success( $events );
    }

    public function impetus_analyze_site() {
        $this->verify_nonce();
        $gen    = new Impetus_AI_Generator();
        $result = $gen->analyze_site();

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result );
        }

        // Save results to options
        if ( ! empty( $result['industry'] ) )        update_option( 'impetus_ai_industry', $result['industry'] );
        if ( ! empty( $result['tone'] ) )            update_option( 'impetus_ai_tone', $result['tone'] );
        if ( ! empty( $result['target_audience'] ) ) update_option( 'impetus_ai_target_audience', $result['target_audience'] );
        if ( ! empty( $result['brand_notes'] ) )     update_option( 'impetus_ai_brand_notes', $result['brand_notes'] );
        if ( ! empty( $result['cta_text'] ) )        update_option( 'impetus_ai_cta_text', $result['cta_text'] );

        wp_send_json_success( $result );
    }

    public function impetus_update_schedule() {
        $this->verify_nonce();
        $id          = intval( $_POST['id'] ?? 0 );
        $scheduled   = sanitize_text_field( $_POST['scheduled_at'] ?? '' );
        if ( $scheduled !== '' && ! $this->valid_datetime( $scheduled ) ) wp_send_json_error( array( 'error' => 'Ervenytelen datum.' ) );
        $post = Impetus_AI_Database::get_post( $id );
        if ( ! $post ) wp_send_json_error( array( 'error' => 'Poszt nem talalhato.' ) );
        $ok = Impetus_AI_Database::update_post( $id, array( 'scheduled_at' => $scheduled !== '' ? $scheduled : null ) );
        if ( false === $ok ) wp_send_json_error( array( 'error' => 'Utemezes mentese sikertelen.' ) );
        wp_send_json_success();
    }
}
