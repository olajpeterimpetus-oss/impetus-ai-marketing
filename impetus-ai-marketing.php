<?php
/**
 * Plugin Name: Impetus AI Marketing
 * Plugin URI: https://impetus.hu
 * Description: AI-alapu social media tartalom generalas es kampanykezeles WordPress-hez. Claude API + fal.ai integracioval.
 * Version: 1.1.0
 * Author: Impetus Weboldalak - Olaj Peter
 * Author URI: https://impetus.hu
 * License: GPL v2 or later
 * Text Domain: impetus-ai-marketing
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'IMPETUS_AI_VERSION', '1.1.0' );
define( 'IMPETUS_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMPETUS_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IMPETUS_AI_DB_VERSION', '1.1' );

// Autoload classes
require_once IMPETUS_AI_PLUGIN_DIR . 'admin/class-database.php';
require_once IMPETUS_AI_PLUGIN_DIR . 'admin/class-generator.php';
require_once IMPETUS_AI_PLUGIN_DIR . 'admin/class-image.php';
require_once IMPETUS_AI_PLUGIN_DIR . 'admin/class-publisher.php';
require_once IMPETUS_AI_PLUGIN_DIR . 'admin/class-admin.php';

// Activation
register_activation_hook( __FILE__, 'impetus_ai_activate' );
register_deactivation_hook( __FILE__, 'impetus_ai_deactivate' );

function impetus_ai_activate() {
    Impetus_AI_Database::create_tables();
    if ( ! wp_next_scheduled( 'impetus_ai_publish_scheduled_posts' ) ) {
        wp_schedule_event( time() + 60, 'impetus_ai_five_minutes', 'impetus_ai_publish_scheduled_posts' );
    }
}

function impetus_ai_deactivate() {
    $timestamp = wp_next_scheduled( 'impetus_ai_publish_scheduled_posts' );
    if ( $timestamp ) wp_unschedule_event( $timestamp, 'impetus_ai_publish_scheduled_posts' );
}

add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['impetus_ai_five_minutes'] ) ) {
        $schedules['impetus_ai_five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Impetus AI - 5 perc',
        );
    }
    return $schedules;
} );

// Init
add_action( 'plugins_loaded', function() {
    new Impetus_AI_Admin();
});
