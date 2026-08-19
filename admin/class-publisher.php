<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Impetus_AI_Publisher {

    private $page_id;
    private $access_token;
    private $instagram_id;
    private $linkedin_token;
    private $linkedin_author;
    private $meta_api_base = 'https://graph.facebook.com/v23.0/';
    private $linkedin_api_base = 'https://api.linkedin.com/rest/';
    private $linkedin_version = '202607';

    public function __construct() {
        $this->page_id         = get_option( 'impetus_ai_fb_page_id', '' );
        $this->access_token    = get_option( 'impetus_ai_fb_token', '' );
        $this->instagram_id    = get_option( 'impetus_ai_ig_id', '' );
        $this->linkedin_token  = get_option( 'impetus_ai_linkedin_token', '' );
        $this->linkedin_author = get_option( 'impetus_ai_linkedin_author', '' );
    }

    private function api_error( $response, $fallback = 'API hiba.' ) {
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $body['error']['message'] ) ) return $body['error']['message'];
        if ( ! empty( $body['message'] ) ) return $body['message'];
        return $code >= 400 ? $fallback . ' HTTP ' . $code . '.' : $fallback;
    }

    public function publish_to_facebook( $message, $image_url = null ) {
        if ( empty( $this->page_id ) || empty( $this->access_token ) ) {
            return array( 'error' => 'Facebook API adatok hianyzanak! Menj a Beallitasok oldalra.' );
        }

        if ( ! empty( $image_url ) && filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            $url      = $this->meta_api_base . rawurlencode( $this->page_id ) . '/photos';
            $response = wp_remote_post( $url, array(
                'timeout' => 30,
                'body'    => array(
                    'caption'      => $message,
                    'access_token' => $this->access_token,
                    'url'          => $image_url,
                ),
            ) );
        } else {
            $url      = $this->meta_api_base . rawurlencode( $this->page_id ) . '/feed';
            $response = wp_remote_post( $url, array(
                'timeout' => 30,
                'body'    => array(
                    'message'      => $message,
                    'access_token' => $this->access_token,
                ),
            ) );
        }

        $error = $this->api_error( $response, 'Facebook publikacio sikertelen.' );
        if ( $error && ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) ) {
            return array( 'error' => $error );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return array( 'ok' => true, 'post_id' => $body['id'] ?? '' );
    }

    public function publish_to_instagram( $message, $image_url ) {
        if ( empty( $this->instagram_id ) || empty( $this->access_token ) ) {
            return array( 'error' => 'Instagram API adatok hianyzanak!' );
        }
        if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            return array( 'error' => 'Instagram csak keppes posztokat tamogat, es ervenyes kep URL szukseges.' );
        }

        $container_url = $this->meta_api_base . rawurlencode( $this->instagram_id ) . '/media';
        $container     = wp_remote_post( $container_url, array(
            'timeout' => 30,
            'body'    => array(
                'image_url'    => $image_url,
                'caption'      => $message,
                'access_token' => $this->access_token,
            ),
        ) );
        if ( is_wp_error( $container ) ) return array( 'error' => $container->get_error_message() );

        $container_body = json_decode( wp_remote_retrieve_body( $container ), true );
        if ( ! empty( $container_body['error']['message'] ) ) return array( 'error' => $container_body['error']['message'] );
        $container_id = $container_body['id'] ?? '';
        if ( empty( $container_id ) ) return array( 'error' => 'Instagram container letrehozasa sikertelen.' );

        // The media container may need processing before publication.
        for ( $i = 0; $i < 10; $i++ ) {
            $status_response = wp_remote_get( $this->meta_api_base . rawurlencode( $container_id ) . '?fields=status_code&access_token=' . rawurlencode( $this->access_token ), array( 'timeout' => 15 ) );
            if ( ! is_wp_error( $status_response ) ) {
                $status_body = json_decode( wp_remote_retrieve_body( $status_response ), true );
                $status = $status_body['status_code'] ?? '';
                if ( $status === 'FINISHED' ) break;
                if ( $status === 'ERROR' ) return array( 'error' => 'Instagram media feldolgozasa sikertelen.' );
            }
            if ( $i < 9 ) sleep( 2 );
        }

        $publish_url = $this->meta_api_base . rawurlencode( $this->instagram_id ) . '/media_publish';
        $publish     = wp_remote_post( $publish_url, array(
            'timeout' => 30,
            'body'    => array(
                'creation_id'  => $container_id,
                'access_token' => $this->access_token,
            ),
        ) );
        if ( is_wp_error( $publish ) ) return array( 'error' => $publish->get_error_message() );
        $publish_body = json_decode( wp_remote_retrieve_body( $publish ), true );
        if ( ! empty( $publish_body['error']['message'] ) ) return array( 'error' => $publish_body['error']['message'] );
        if ( empty( $publish_body['id'] ) ) return array( 'error' => 'Instagram publikacio nem adott vissza media ID-t.' );

        return array( 'ok' => true, 'post_id' => $publish_body['id'] );
    }

    private function linkedin_headers() {
        return array(
            'Authorization'              => 'Bearer ' . $this->linkedin_token,
            'Content-Type'               => 'application/json',
            'Linkedin-Version'           => $this->linkedin_version,
            'X-Restli-Protocol-Version'  => '2.0.0',
        );
    }

    private function linkedin_upload_image( $filename ) {
        if ( empty( $this->linkedin_token ) || empty( $this->linkedin_author ) ) {
            return array( 'error' => 'LinkedIn API token vagy author URN hianyzik.' );
        }

        $image = new Impetus_AI_Image();
        $path  = $image->get_image_path( $filename );
        if ( ! $path || ! file_exists( $path ) ) return array( 'error' => 'A LinkedIn-re feltoltendo kep nem talalhato.' );

        $init = wp_remote_post( $this->linkedin_api_base . 'images?action=initializeUpload', array(
            'timeout' => 30,
            'headers' => $this->linkedin_headers(),
            'body'    => wp_json_encode( array(
                'initializeUploadRequest' => array( 'owner' => $this->linkedin_author ),
            ) ),
        ) );
        if ( is_wp_error( $init ) ) return array( 'error' => $init->get_error_message() );
        $init_body = json_decode( wp_remote_retrieve_body( $init ), true );
        $upload_url = $init_body['value']['uploadUrl'] ?? '';
        $image_urn  = $init_body['value']['image'] ?? '';
        if ( empty( $upload_url ) || empty( $image_urn ) ) return array( 'error' => $this->api_error( $init, 'LinkedIn kepfeltoltes inicializalasa sikertelen.' ) );

        $binary = file_get_contents( $path );
        if ( false === $binary ) return array( 'error' => 'A kepfajl nem olvashato.' );
        $upload = wp_remote_request( $upload_url, array(
            'method'  => 'PUT',
            'timeout' => 60,
            'headers' => array( 'Content-Type' => 'application/octet-stream' ),
            'body'    => $binary,
        ) );
        if ( is_wp_error( $upload ) || wp_remote_retrieve_response_code( $upload ) >= 400 ) {
            return array( 'error' => $this->api_error( $upload, 'LinkedIn kepfeltoltes sikertelen.' ) );
        }
        return array( 'urn' => $image_urn );
    }

    public function publish_to_linkedin( $message, $image_filename = '' ) {
        if ( empty( $this->linkedin_token ) || empty( $this->linkedin_author ) ) {
            return array( 'error' => 'LinkedIn API token vagy author URN nincs beallitva.' );
        }

        $post = array(
            'author' => $this->linkedin_author,
            'commentary' => $message,
            'visibility' => 'PUBLIC',
            'distribution' => array(
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => array(),
                'thirdPartyDistributionChannels' => array(),
            ),
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        );

        if ( ! empty( $image_filename ) ) {
            $upload = $this->linkedin_upload_image( $image_filename );
            if ( isset( $upload['error'] ) ) return $upload;
            $post['content'] = array(
                'media' => array(
                    'altText' => 'Social media kep',
                    'id' => $upload['urn'],
                ),
            );
        }

        $response = wp_remote_post( $this->linkedin_api_base . 'posts', array(
            'timeout' => 60,
            'headers' => $this->linkedin_headers(),
            'body'    => wp_json_encode( $post ),
        ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
            return array( 'error' => $this->api_error( $response, 'LinkedIn publikacio sikertelen.' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $post_id = wp_remote_retrieve_header( $response, 'x-restli-id' );
        if ( empty( $post_id ) && ! empty( $body['id'] ) ) $post_id = $body['id'];
        return array( 'ok' => true, 'post_id' => $post_id );
    }

    public function publish_post( $post_id ) {
        $post = Impetus_AI_Database::get_post( $post_id );
        if ( ! $post ) return array( 'error' => 'Poszt nem talalhato.' );
        if ( $post->status !== 'approved' ) return array( 'error' => 'Csak jovahagyott poszt publikálható.' );

        $message = trim( (string) $post->caption );
        if ( ! empty( $post->hashtags ) ) $message .= "\n\n" . trim( $post->hashtags );

        if ( $post->platform === 'instagram' ) {
            $image_obj = new Impetus_AI_Image();
            $image_url = ! empty( $post->image_filename ) ? $image_obj->get_image_url( $post->image_filename ) : '';
            $result = $this->publish_to_instagram( $message, $image_url );
        } elseif ( $post->platform === 'linkedin' ) {
            $result = $this->publish_to_linkedin( $message, $post->image_filename );
        } elseif ( $post->platform === 'facebook' ) {
            $image_obj = new Impetus_AI_Image();
            $image_url = ! empty( $post->image_filename ) ? $image_obj->get_image_url( $post->image_filename ) : '';
            $result = $this->publish_to_facebook( $message, $image_url );
        } else {
            return array( 'error' => 'Nem tamogatott platform.' );
        }

        if ( isset( $result['ok'] ) && $result['ok'] ) {
            Impetus_AI_Database::update_post( $post_id, array(
                'status'       => 'published',
                'published_at' => current_time( 'mysql' ),
            ) );
        }
        return $result;
    }

    public static function cron_publish_scheduled() {
        if ( ! class_exists( 'Impetus_AI_Database' ) ) return;
        $posts = Impetus_AI_Database::get_due_posts( 10 );
        if ( empty( $posts ) ) return;
        $publisher = new self();
        foreach ( $posts as $post ) {
            $publisher->publish_post( $post->id );
        }
    }
}
