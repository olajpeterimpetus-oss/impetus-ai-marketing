<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Impetus_AI_Database {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $posts_table = $wpdb->prefix . 'impetus_posts';
        $campaigns_table = $wpdb->prefix . 'impetus_campaigns';

        $sql = "
        CREATE TABLE IF NOT EXISTS {$campaigns_table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            goal TEXT,
            platforms VARCHAR(255) DEFAULT 'facebook',
            start_date DATE,
            end_date DATE,
            status VARCHAR(50) DEFAULT 'active',
            ai_strategy LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset};

        CREATE TABLE IF NOT EXISTS {$posts_table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT(20) DEFAULT NULL,
            platform VARCHAR(50) NOT NULL,
            topic VARCHAR(500),
            caption LONGTEXT,
            hashtags TEXT,
            image_url VARCHAR(500),
            image_filename VARCHAR(255),
            status VARCHAR(50) DEFAULT 'draft',
            scheduled_at DATETIME DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'impetus_ai_db_version', IMPETUS_AI_DB_VERSION );
    }

    public static function get_posts( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_posts';
        $campaigns_table = $wpdb->prefix . 'impetus_campaigns';

        $where = '1=1';
        $params = array();

        if ( ! empty( $args['campaign_id'] ) ) {
            $where .= ' AND p.campaign_id = %d';
            $params[] = $args['campaign_id'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where .= ' AND p.status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['month'] ) ) {
            $where .= ' AND DATE_FORMAT(p.scheduled_at, "%%Y-%%m") = %s';
            $params[] = $args['month'];
        }

        $sql = "SELECT p.*, c.name as campaign_name
                FROM {$table} p
                LEFT JOIN {$campaigns_table} c ON p.campaign_id = c.id
                WHERE {$where}
                ORDER BY p.scheduled_at ASC, p.created_at DESC";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql );
    }

    public static function get_campaigns() {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_campaigns';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
    }

    public static function save_post( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_posts';
        $result = $wpdb->insert( $table, $data );
        return false === $result ? 0 : $wpdb->insert_id;
    }

    public static function update_post( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_posts';
        return $wpdb->update( $table, $data, array( 'id' => $id ) );
    }

    public static function get_due_posts( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_posts';
        $limit = max( 1, min( 50, intval( $limit ) ) );
        $now = current_time( 'mysql' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'approved' AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
            $now,
            $limit
        ) );
    }

    public static function get_post( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_posts';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    public static function delete_post( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_posts';
        return $wpdb->delete( $table, array( 'id' => $id ) );
    }

    public static function save_campaign( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'impetus_campaigns';
        $result = $wpdb->insert( $table, $data );
        return false === $result ? 0 : $wpdb->insert_id;
    }

    public static function delete_campaign( $id ) {
        global $wpdb;
        $posts_table = $wpdb->prefix . 'impetus_posts';
        $campaigns_table = $wpdb->prefix . 'impetus_campaigns';
        $wpdb->delete( $posts_table, array( 'campaign_id' => $id ) );
        $wpdb->delete( $campaigns_table, array( 'id' => $id ) );
    }
}
