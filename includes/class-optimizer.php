<?php

/**
 * Class WP_DBO_Optimizer
 * 
 * Modul untuk menangani semua tugas optimasi database
 */
class WP_DBO_Optimizer
{

    private $logger;
    private $options;

    /**
     * Constructor
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->options = get_option('wp_db_optimizer_options', array());
    }

    /**
     * Jalankan semua tugas optimasi secara otomatis
     */
    public function run_all_optimizations()
    {
        global $wpdb;

        $this->logger->log('Memulai proses optimasi database');

        $start_time = microtime(true);
        $results = array(
            'status' => 'success',
            'optimized_tables' => 0,
            'repaired_tables' => 0,
            'removed_items' => 0,
            'errors' => array(),
        );

        try {
            // Optimize tables
            if ($this->get_option('optimize_tables')) {
                $optimized = $this->optimize_tables();
                $results['optimized_tables'] = $optimized;
            }

            // Repair tables
            if ($this->get_option('repair_tables')) {
                $repaired = $this->repair_tables();
                $results['repaired_tables'] = $repaired;
            }

            // Clean post revisions
            if ($this->get_option('optimize_post_revisions')) {
                $deleted = $this->clean_post_revisions();
                $results['removed_items'] += $deleted;
            }

            // Clean auto drafts
            if ($this->get_option('optimize_auto_drafts')) {
                $deleted = $this->clean_auto_drafts();
                $results['removed_items'] += $deleted;
            }

            // Clean trashed posts
            if ($this->get_option('optimize_trashed_posts')) {
                $deleted = $this->clean_trashed_posts();
                $results['removed_items'] += $deleted;
            }

            // Clean spam comments
            if ($this->get_option('optimize_spam_comments')) {
                $deleted = $this->clean_spam_comments();
                $results['removed_items'] += $deleted;
            }

            // Clean trashed comments
            if ($this->get_option('optimize_trashed_comments')) {
                $deleted = $this->clean_trashed_comments();
                $results['removed_items'] += $deleted;
            }

            // Clean expired transients
            if ($this->get_option('optimize_expired_transients')) {
                $deleted = $this->clean_expired_transients();
                $results['removed_items'] += $deleted;
            }
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['errors'][] = $e->getMessage();
            $this->logger->log('Error selama optimasi: ' . $e->getMessage(), 'error');
        }

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        $results['execution_time'] = $execution_time;

        $this->logger->log('Proses optimasi database selesai dalam ' . $execution_time . ' detik');

        // Kirim notifikasi jika diaktifkan
        if ($this->get_option('send_email_notification')) {
            $this->send_notification_email($results);
        }

        return $results;
    }

    /**
     * Optimize WordPress database tables
     */
    public function optimize_tables()
    {
        global $wpdb;

        $tables = $this->get_wp_tables();
        $optimized = 0;

        foreach ($tables as $table) {
            $this->logger->log('Mengoptimasi tabel: ' . $table);

            $result = $wpdb->query("OPTIMIZE TABLE $table");

            if ($result !== false) {
                $optimized++;
            } else {
                $this->logger->log('Gagal mengoptimasi tabel: ' . $table, 'error');
            }
        }

        return $optimized;
    }

    /**
     * Repair WordPress database tables
     */
    public function repair_tables()
    {
        global $wpdb;

        $tables = $this->get_wp_tables();
        $repaired = 0;

        foreach ($tables as $table) {
            $this->logger->log('Memperbaiki tabel: ' . $table);

            $result = $wpdb->query("REPAIR TABLE $table");

            if ($result !== false) {
                $repaired++;
            } else {
                $this->logger->log('Gagal memperbaiki tabel: ' . $table, 'error');
            }
        }

        return $repaired;
    }

    /**
     * Get all WordPress tables
     */
    private function get_wp_tables()
    {
        global $wpdb;

        $tables = array();
        $results = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);

        if (!empty($results)) {
            foreach ($results as $result) {
                $tables[] = $result[0];
            }
        }

        return $tables;
    }

    /**
     * Clean post revisions
     */
    public function clean_post_revisions()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->posts WHERE post_type = 'revision'";
        $deleted = $wpdb->query($query);

        $this->logger->log("Menghapus $deleted revisi post");

        return $deleted;
    }

    /**
     * Clean auto drafts
     */
    public function clean_auto_drafts()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->posts WHERE post_status = 'auto-draft'";
        $deleted = $wpdb->query($query);

        $this->logger->log("Menghapus $deleted auto-draft");

        return $deleted;
    }

    /**
     * Clean trashed posts
     */
    public function clean_trashed_posts()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->posts WHERE post_status = 'trash'";
        $deleted = $wpdb->query($query);

        $this->logger->log("Menghapus $deleted post di sampah");

        return $deleted;
    }

    /**
     * Clean spam comments
     */
    public function clean_spam_comments()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->comments WHERE comment_approved = 'spam'";
        $deleted = $wpdb->query($query);

        $this->logger->log("Menghapus $deleted komentar spam");

        return $deleted;
    }

    /**
     * Clean trashed comments
     */
    public function clean_trashed_comments()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->comments WHERE comment_approved = 'trash'";
        $deleted = $wpdb->query($query);

        $this->logger->log("Menghapus $deleted komentar di sampah");

        return $deleted;
    }

    /**
     * Clean expired transients
     */
    public function clean_expired_transients()
    {
        global $wpdb;

        $time = time();
        $deleted = 0;

        // Get all expired transients
        $expired = $wpdb->get_col(
            "SELECT option_name FROM $wpdb->options 
            WHERE option_name LIKE '_transient_timeout_%' 
            AND option_value < $time"
        );

        if (!empty($expired)) {
            foreach ($expired as $transient) {
                $name = str_replace('_transient_timeout_', '', $transient);
                delete_transient($name);
                $deleted++;
            }
        }

        $this->logger->log("Menghapus $deleted transient yang kedaluwarsa");

        return $deleted;
    }

    /**
     * Send notification email with results
     */
    private function send_notification_email($results)
    {
        $to = $this->get_option('notification_email');

        if (empty($to)) {
            $to = get_option('admin_email');
        }

        $subject = 'WordPress Database Optimization Report - ' . get_bloginfo('name');

        $message = "== WordPress Database Optimization Report ==\n\n";
        $message .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Status: " . $results['status'] . "\n";
        $message .= "Waktu eksekusi: " . $results['execution_time'] . " detik\n\n";

        $message .= "-- Detail Optimasi --\n";
        $message .= "Tabel dioptimasi: " . $results['optimized_tables'] . "\n";
        $message .= "Tabel diperbaiki: " . $results['repaired_tables'] . "\n";
        $message .= "Item dihapus: " . $results['removed_items'] . "\n\n";

        if (!empty($results['errors'])) {
            $message .= "-- Error --\n";
            foreach ($results['errors'] as $error) {
                $message .= "- " . $error . "\n";
            }
        }

        $message .= "\nLaporan ini dibuat secara otomatis oleh plugin WP Database Optimizer.\n";

        // Send email
        $sent = wp_mail($to, $subject, $message);

        if ($sent) {
            $this->logger->log("Notifikasi email berhasil dikirim ke $to");
        } else {
            $this->logger->log("Gagal mengirim notifikasi email ke $to", 'error');
        }
    }

    /**
     * Get option value
     */
    private function get_option($key, $default = false)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return $default;
    }
}
