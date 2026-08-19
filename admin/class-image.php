<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Impetus_AI_Image {

    private $fal_key;
    private $upload_dir;

    public function __construct() {
        $this->fal_key  = get_option( 'impetus_ai_fal_key', '' );
        $uploads        = wp_upload_dir();
        $this->upload_dir = $uploads['basedir'] . '/impetus-ai/';
        if ( ! file_exists( $this->upload_dir ) ) {
            wp_mkdir_p( $this->upload_dir );
        }
    }

    public function generate( $image_prompt, $topic, $provider = 'ideogram' ) {
        if ( empty( $this->fal_key ) ) {
            return array( 'error' => 'fal.ai API kulcs nincs beallitva!' );
        }

        switch ( $provider ) {
            case 'ideogram':
                return $this->generate_ideogram( $image_prompt, $topic );
            case 'flux':
                return $this->generate_flux( $image_prompt );
            default:
                return array( 'error' => 'Ismeretlen kepgeneralasi mod.' );
        }
    }

    private function generate_ideogram( $image_prompt, $topic ) {
        $site_name = get_bloginfo( 'name' );
        $cta_text  = get_option( 'impetus_ai_cta_text', 'Keress minket!' );

        $full_prompt = "Professional social media marketing post, square 1:1 format. "
            . "Large bold white headline text \"" . $topic . "\" at the top left with dark shadow. "
            . $image_prompt . ". "
            . "Bottom: gold rounded button with text \"" . $cta_text . "\". "
            . "Top right: small brand text \"" . $site_name . "\". "
            . "Professional marketing design, high contrast, photorealistic background, social media ready.";

        // Submit to fal.ai queue
        $submit = wp_remote_post( 'https://queue.fal.run/fal-ai/ideogram-v4/instant', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Key ' . $this->fal_key,
            ),
            'body' => json_encode( array(
                'prompt'     => $full_prompt,
                'image_size' => 'square_hd',
                'style'      => 'realistic',
            ) ),
        ) );

        if ( is_wp_error( $submit ) ) {
            return array( 'error' => $submit->get_error_message() );
        }

        $submit_body = json_decode( wp_remote_retrieve_body( $submit ), true );
        if ( empty( $submit_body['request_id'] ) ) {
            return array( 'error' => 'fal.ai queue hiba: ' . wp_remote_retrieve_body( $submit ) );
        }

        $request_id  = $submit_body['request_id'];
        $status_url  = 'https://queue.fal.run/fal-ai/ideogram-v4/instant/requests/' . $request_id;

        // Poll for result (max 60 sec)
        $image_url = null;
        for ( $i = 0; $i < 20; $i++ ) {
            sleep( 3 );
            $status = wp_remote_get( $status_url . '/status', array(
                'timeout' => 15,
                'headers' => array( 'Authorization' => 'Key ' . $this->fal_key ),
            ) );
            $status_body = json_decode( wp_remote_retrieve_body( $status ), true );

            if ( isset( $status_body['status'] ) && $status_body['status'] === 'COMPLETED' ) {
                $result = wp_remote_get( $status_url, array(
                    'timeout' => 15,
                    'headers' => array( 'Authorization' => 'Key ' . $this->fal_key ),
                ) );
                $result_body = json_decode( wp_remote_retrieve_body( $result ), true );
                if ( ! empty( $result_body['images'][0]['url'] ) ) {
                    $image_url = $result_body['images'][0]['url'];
                    break;
                }
            }
            if ( isset( $status_body['status'] ) && $status_body['status'] === 'FAILED' ) {
                return array( 'error' => 'Ideogram generalas sikertelen.' );
            }
        }

        if ( empty( $image_url ) ) {
            return array( 'error' => 'Kepgeneralas timeout.' );
        }

        return $this->download_and_save( $image_url );
    }

    private function generate_flux( $image_prompt ) {
        $full_prompt = $image_prompt . ", no text, no words, clean photorealistic background, social media post background";

        $submit = wp_remote_post( 'https://queue.fal.run/fal-ai/flux-pro/v1.1', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Key ' . $this->fal_key,
            ),
            'body' => json_encode( array(
                'prompt'           => $full_prompt,
                'image_size'       => 'square_hd',
                'num_images'       => 1,
                'output_format'    => 'jpeg',
                'safety_tolerance' => '2',
            ) ),
        ) );

        if ( is_wp_error( $submit ) ) {
            return array( 'error' => $submit->get_error_message() );
        }

        $submit_body = json_decode( wp_remote_retrieve_body( $submit ), true );
        if ( empty( $submit_body['request_id'] ) ) {
            return array( 'error' => 'FLUX queue hiba.' );
        }

        $request_id = $submit_body['request_id'];
        $status_url = 'https://queue.fal.run/fal-ai/flux-pro/v1.1/requests/' . $request_id;

        for ( $i = 0; $i < 20; $i++ ) {
            sleep( 3 );
            $status      = wp_remote_get( $status_url . '/status', array(
                'timeout' => 15,
                'headers' => array( 'Authorization' => 'Key ' . $this->fal_key ),
            ) );
            $status_body = json_decode( wp_remote_retrieve_body( $status ), true );
            if ( isset( $status_body['status'] ) && $status_body['status'] === 'COMPLETED' ) {
                $result      = wp_remote_get( $status_url, array(
                    'timeout' => 15,
                    'headers' => array( 'Authorization' => 'Key ' . $this->fal_key ),
                ) );
                $result_body = json_decode( wp_remote_retrieve_body( $result ), true );
                if ( ! empty( $result_body['images'][0]['url'] ) ) {
                    return $this->download_and_save( $result_body['images'][0]['url'] );
                }
            }
        }

        return array( 'error' => 'FLUX timeout.' );
    }

    private function download_and_save( $url ) {
        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'Kep letoltesi hiba: ' . $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return array( 'error' => 'Kep letoltesi hiba. HTTP ' . $code );
        }
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) return array( 'error' => 'A letoltott kep ures.' );

        $filename = 'impetus-post-' . time() . '-' . wp_rand( 1000, 9999 ) . '.jpg';
        $filepath = $this->get_image_path( $filename );

        if ( false === file_put_contents( $filepath, $body, LOCK_EX ) ) {
            return array( 'error' => 'A kep mentese sikertelen.' );
        }

        $uploads   = wp_upload_dir();
        $image_url = $uploads['baseurl'] . '/impetus-ai/' . $filename;

        return array(
            'url'      => $image_url,
            'filename' => $filename,
            'path'     => $filepath,
        );
    }

    public function delete_image( $filename ) {
        $filepath = $this->get_image_path( $filename );
        if ( file_exists( $filepath ) ) {
            unlink( $filepath );
        }
    }

    public function get_image_path( $filename ) {
        $filename = sanitize_file_name( $filename );
        if ( empty( $filename ) ) return '';
        return $this->upload_dir . $filename;
    }

    public function get_image_url( $filename ) {
        $uploads = wp_upload_dir();
        return $uploads['baseurl'] . '/impetus-ai/' . $filename;
    }
}
