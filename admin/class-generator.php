<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Impetus_AI_Generator {

    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';

    public function __construct() {
        $this->api_key = get_option( 'impetus_ai_anthropic_key', '' );
    }

    private function get_site_context() {
        $site_name = get_bloginfo( 'name' );
        $site_desc = get_bloginfo( 'description' );
        $site_url  = get_bloginfo( 'url' );

        // Get stored brand profile
        $industry       = get_option( 'impetus_ai_industry', '' );
        $tone           = get_option( 'impetus_ai_tone', 'friendly' );
        $target_audience = get_option( 'impetus_ai_target_audience', '' );
        $brand_notes    = get_option( 'impetus_ai_brand_notes', '' );
        $cta_text       = get_option( 'impetus_ai_cta_text', 'Keress minket!' );

        return array(
            'site_name'      => $site_name,
            'site_desc'      => $site_desc,
            'site_url'       => $site_url,
            'industry'       => $industry,
            'tone'           => $tone,
            'target_audience' => $target_audience,
            'brand_notes'    => $brand_notes,
            'cta_text'       => $cta_text,
        );
    }

    public function generate_post( $platform, $topic, $extra = '' ) {
        if ( empty( $this->api_key ) ) {
            return array( 'error' => 'Anthropic API kulcs nincs beallitva! Menj a Beallitasok oldalra.' );
        }

        $ctx = $this->get_site_context();

        $platform_hints = array(
            'facebook'  => 'Facebook poszt: 100-200 szo, barati hangnem, 3-5 hashtag a vegen.',
            'instagram' => 'Instagram caption: rovid utos elso sor, emoji-k, 5-10 hashtag a vegen.',
            'linkedin'  => 'LinkedIn poszt: szakmai, ertekorizentalt, 150-300 szo, 3 hashtag.',
        );
        $hint = isset( $platform_hints[ $platform ] ) ? $platform_hints[ $platform ] : '';

        $brand_section = '';
        if ( ! empty( $ctx['brand_notes'] ) ) {
            $brand_section = "Brand megjegyzesek: " . $ctx['brand_notes'] . "\n";
        }

        $prompt = "Te egy tapasztalt magyar social media menedzser vagy.\n\n"
            . "Weblap: " . $ctx['site_name'] . " (" . $ctx['site_url'] . ")\n"
            . "Leiras: " . $ctx['site_desc'] . "\n"
            . "Iparag: " . $ctx['industry'] . "\n"
            . "Hangnem: " . $ctx['tone'] . "\n"
            . "Celcsoport: " . $ctx['target_audience'] . "\n"
            . $brand_section . "\n"
            . "Feladat: Irj " . ucfirst( $platform ) . " posztot errol a temarol: \"" . $topic . "\"\n"
            . ( ! empty( $extra ) ? "Extra instrukció: " . $extra . "\n" : '' )
            . "\n" . $hint . "\n\n"
            . "Valaszolj KIZAROLAG JSON formatumban:\n"
            . "{\n"
            . "  \"caption\": \"a poszt szovege\",\n"
            . "  \"hashtags\": \"#hashtag1 #hashtag2\",\n"
            . "  \"image_prompt\": \"angol FLUX/Ideogram prompt fotorealisztikus kephez, szoveg nelkul\"\n"
            . "}";

        $response = wp_remote_post( $this->api_url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode( array(
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['content'][0]['text'] ) ) {
            return array( 'error' => 'Ures valasz az API-tol.' );
        }

        $raw = trim( $body['content'][0]['text'] );
        return $this->parse_json_response( $raw );
    }

    public function generate_campaign_strategy( $name, $goal, $platforms, $start_date, $end_date ) {
        if ( empty( $this->api_key ) ) {
            return array( 'error' => 'Anthropic API kulcs nincs beallitva!' );
        }

        $ctx  = $this->get_site_context();
        $days = max( 1, ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400 );
        $optimal = min( max( 4, intval( $days / 2 ) ), 14 );
        $plat_str = implode( ', ', $platforms );

        $prompt = "You are a social media campaign strategist. Create a campaign plan.\n\n"
            . "Client: " . $ctx['site_name'] . "\n"
            . "Industry: " . $ctx['industry'] . "\n"
            . "Target audience: " . $ctx['target_audience'] . "\n"
            . "Campaign: " . $name . "\n"
            . "Goal: " . $goal . "\n"
            . "Duration: " . $start_date . " to " . $end_date . " (" . intval($days) . " days)\n"
            . "Platforms: " . $plat_str . "\n\n"
            . "Respond ONLY with valid JSON:\n"
            . "{\n"
            . "  \"strategy_summary\": \"2-3 mondatos strategia magyarul\",\n"
            . "  \"posts\": [\n"
            . "    {\"day_offset\": 0, \"platform\": \"facebook\", \"topic\": \"tema magyarul\", \"post_type\": \"intro\", \"suggested_time\": \"10:00\"}\n"
            . "  ]\n"
            . "}\n"
            . "Rules: Only use platforms: " . $plat_str . ". Optimal post count: " . $optimal . ". day_offset 0-" . intval($days) . ".";

        $response = wp_remote_post( $this->api_url, array(
            'timeout' => 90,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode( array(
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 2000,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['content'][0]['text'] ) ) {
            return array( 'error' => 'Ures valasz.' );
        }

        $raw = trim( $body['content'][0]['text'] );
        return $this->parse_json_response( $raw );
    }

    public function analyze_site() {
        if ( empty( $this->api_key ) ) {
            return array( 'error' => 'Anthropic API kulcs nincs beallitva!' );
        }

        // Collect site content
        $site_name = get_bloginfo( 'name' );
        $site_desc = get_bloginfo( 'description' );

        // Get recent pages content
        $pages = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'numberposts' => 5,
            'post_status' => 'publish',
        ) );

        $content_parts = array( "Weboldal neve: " . $site_name, "Leiras: " . $site_desc );
        foreach ( $pages as $page ) {
            $text = wp_strip_all_tags( $page->post_content );
            $text = substr( $text, 0, 500 );
            if ( ! empty( trim( $text ) ) ) {
                $content_parts[] = "Oldal: " . $page->post_title . "\n" . $text;
            }
        }

        $site_content = implode( "\n\n", $content_parts );

        $prompt = "Elemezd ezt a magyar weboldalt es hatarozd meg a brand profilt.\n\n"
            . $site_content . "\n\n"
            . "Valaszolj KIZAROLAG JSON formatumban:\n"
            . "{\n"
            . "  \"industry\": \"iparag\",\n"
            . "  \"tone\": \"friendly/professional/casual/formal/enthusiastic\",\n"
            . "  \"target_audience\": \"celcsoport\",\n"
            . "  \"brand_notes\": \"brand voice, fo uzenetek, egyedi jellemzok\",\n"
            . "  \"cta_text\": \"CTA gomb szovege (max 30 kar)\"\n"
            . "}";

        $response = wp_remote_post( $this->api_url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode( array(
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 1000,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['content'][0]['text'] ) ) {
            return array( 'error' => 'Ures valasz.' );
        }

        return $this->parse_json_response( trim( $body['content'][0]['text'] ) );
    }

    private function parse_json_response( $raw ) {
        // Strip markdown fences
        if ( strpos( $raw, '```' ) !== false ) {
            $parts = explode( '```', $raw );
            foreach ( $parts as $part ) {
                $part = trim( $part );
                if ( substr( $part, 0, 4 ) === 'json' ) $part = trim( substr( $part, 4 ) );
                if ( substr( $part, 0, 1 ) === '{' ) { $raw = $part; break; }
            }
        }

        // Find JSON boundaries
        $start = strpos( $raw, '{' );
        $end   = strrpos( $raw, '}' );
        if ( $start !== false && $end !== false ) {
            $raw = substr( $raw, $start, $end - $start + 1 );
        }

        $result = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Regex fallback
            $caption = $hashtags = $image_prompt = '';
            if ( preg_match( '/"caption"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $m ) ) {
                $caption = stripslashes( $m[1] );
            }
            if ( preg_match( '/"hashtags"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $m ) ) {
                $hashtags = stripslashes( $m[1] );
            }
            if ( preg_match( '/"image_prompt"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $m ) ) {
                $image_prompt = stripslashes( $m[1] );
            }
            if ( empty( $caption ) ) {
                return array( 'error' => 'AI valasz nem ertelmezhetoe. Probald ujra.' );
            }
            return array( 'caption' => $caption, 'hashtags' => $hashtags, 'image_prompt' => $image_prompt );
        }

        return $result;
    }
}
