<?php

/**
 * Class WP_DBO_Logger
 * 
 * Modul untuk mencatat log aktivitas
 */
class WP_DBO_Logger
{

    private $log_table;
    private $options;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;

        $this->log_table = $wpdb->prefix . 'db_optimizer_logs';
        $this->options = get_option('wp_db_optimizer_options', array());

        // Tambahkan hook untuk membersihkan log lama
        add_action('wp_db_optimizer_monthly_event', array($this, 'clean_old_logs'));
    }

    /**
     * Buat tabel log jika belum ada
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_time datetime NOT NULL,
            log_type varchar(20) NOT NULL DEFAULT 'info',
            log_message text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Catat pesan log
     */
    public function log($message, $type = 'info')
    {
        global $wpdb;

        $data = array(
            'log_time' => current_time('mysql'),
            'log_type' => $type,
            'log_message' => $message
        );

        $wpdb->insert($this->log_table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Dapatkan log terbaru
     */
    public function get_logs($limit = 100, $offset = 0)
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->log_table} ORDER BY log_time DESC LIMIT %d OFFSET %d";

        $results = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));

        return $results;
    }

    /**
     * Bersihkan log lama
     */
    public function clean_old_logs()
    {
        global $wpdb;

        // Dapatkan waktu penyimpanan log dalam hari
        $keep_logs = isset($this->options['keep_logs']) ? (int) $this->options['keep_logs'] : 30;

        if ($keep_logs > 0) {
            $date = date('Y-m-d H:i:s', strtotime("-{$keep_logs} days"));

            $sql = "DELETE FROM {$this->log_table} WHERE log_time < %s";

            $deleted = $wpdb->query($wpdb->prepare($sql, $date));

            $this->log("Membersihkan log: {$deleted} entri log dihapus");
        }
    }

    /**
     * Hapus semua log
     */
    public function delete_all_logs()
    {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$this->log_table}");

        $this->log('Semua log dihapus');
    }

    /**
     * Dapatkan jumlah log
     */
    public function get_log_count()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");

        return $count;
    }
}
