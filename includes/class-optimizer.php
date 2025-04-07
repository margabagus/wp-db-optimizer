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
            // Optimize tables - dengan pengecekan error yang lebih baik
            if ($this->get_option('optimize_tables')) {
                $optimized = $this->optimize_tables();
                $results['optimized_tables'] = $optimized;
            }

            // Clean post revisions - dengan cara yang lebih aman
            if ($this->get_option('optimize_post_revisions')) {
                $deleted = $this->clean_post_revisions();
                $results['removed_items'] += $deleted;
            }

            // Clean auto drafts - dengan cara yang lebih aman
            if ($this->get_option('optimize_auto_drafts')) {
                $deleted = $this->clean_auto_drafts();
                $results['removed_items'] += $deleted;
            }

            // Clean trashed posts - dengan cara yang lebih aman
            if ($this->get_option('optimize_trashed_posts')) {
                $deleted = $this->clean_trashed_posts();
                $results['removed_items'] += $deleted;
            }

            // Clean spam comments - dengan cara yang lebih aman
            if ($this->get_option('optimize_spam_comments')) {
                $deleted = $this->clean_spam_comments();
                $results['removed_items'] += $deleted;
            }

            // Clean trashed comments - dengan cara yang lebih aman
            if ($this->get_option('optimize_trashed_comments')) {
                $deleted = $this->clean_trashed_comments();
                $results['removed_items'] += $deleted;
            }

            // Clean expired transients - dengan cara yang lebih aman
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
     * Optimize WordPress database tables - Dengan metode yang lebih kompatibel
     */
    public function optimize_tables()
    {
        global $wpdb;

        $tables = $this->get_wp_tables();
        $optimized = 0;

        foreach ($tables as $table) {
            $this->logger->log('Mengoptimasi tabel: ' . $table);

            // Coba gunakan metode alternatif jika OPTIMIZE TABLE gagal
            $query = "OPTIMIZE TABLE $table";
            $result = $wpdb->query($query);

            // Jika gagal, coba metode alternatif
            if ($result === false) {
                $this->logger->log('Mencoba metode alternatif untuk tabel: ' . $table);

                // Metode alternatif: Rebuild dengan ALTER TABLE
                $alter_query = "ALTER TABLE $table ENGINE = InnoDB";
                $result = $wpdb->query($alter_query);

                if ($result !== false) {
                    $optimized++;
                } else {
                    $this->logger->log('Gagal mengoptimasi tabel: ' . $table, 'error');
                }
            } else {
                $optimized++;
            }
        }

        return $optimized;
    }

    /**
     * Get all WordPress tables with error handling yang lebih baik
     */
    private function get_wp_tables()
    {
        global $wpdb;

        $tables = array();
        $prefix = $wpdb->prefix;

        try {
            $results = $wpdb->get_results("SHOW TABLES LIKE '{$prefix}%'", ARRAY_N);

            if (!empty($results) && is_array($results)) {
                foreach ($results as $result) {
                    if (isset($result[0])) {
                        $tables[] = $result[0];
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->log('Error saat mengambil daftar tabel: ' . $e->getMessage(), 'error');
        }

        return $tables;
    }

    /**
     * Clean post revisions - dengan batasan jumlah per eksekusi
     */
    public function clean_post_revisions()
    {
        global $wpdb;

        // Buat query yang lebih aman dengan batasan
        $query = "DELETE FROM $wpdb->posts WHERE post_type = 'revision' LIMIT 1000";
        $deleted = $wpdb->query($query);

        if ($deleted === false) {
            $this->logger->log("Error saat menghapus revisi post", 'error');
            return 0;
        }

        $this->logger->log("Menghapus $deleted revisi post");

        return $deleted;
    }

    /**
     * Clean auto drafts - dengan batasan jumlah per eksekusi
     */
    public function clean_auto_drafts()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->posts WHERE post_status = 'auto-draft' LIMIT 500";
        $deleted = $wpdb->query($query);

        if ($deleted === false) {
            $this->logger->log("Error saat menghapus auto-draft", 'error');
            return 0;
        }

        $this->logger->log("Menghapus $deleted auto-draft");

        return $deleted;
    }

    /**
     * Clean trashed posts - dengan batasan jumlah per eksekusi
     */
    public function clean_trashed_posts()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->posts WHERE post_status = 'trash' LIMIT 500";
        $deleted = $wpdb->query($query);

        if ($deleted === false) {
            $this->logger->log("Error saat menghapus post di sampah", 'error');
            return 0;
        }

        $this->logger->log("Menghapus $deleted post di sampah");

        return $deleted;
    }

    /**
     * Clean spam comments - dengan batasan jumlah per eksekusi
     */
    public function clean_spam_comments()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->comments WHERE comment_approved = 'spam' LIMIT 1000";
        $deleted = $wpdb->query($query);

        if ($deleted === false) {
            $this->logger->log("Error saat menghapus komentar spam", 'error');
            return 0;
        }

        $this->logger->log("Menghapus $deleted komentar spam");

        return $deleted;
    }

    /**
     * Clean trashed comments - dengan batasan jumlah per eksekusi
     */
    public function clean_trashed_comments()
    {
        global $wpdb;

        $query = "DELETE FROM $wpdb->comments WHERE comment_approved = 'trash' LIMIT 500";
        $deleted = $wpdb->query($query);

        if ($deleted === false) {
            $this->logger->log("Error saat menghapus komentar di sampah", 'error');
            return 0;
        }

        $this->logger->log("Menghapus $deleted komentar di sampah");

        return $deleted;
    }

    /**
     * Clean expired transients - dengan penanganan error yang lebih baik
     */
    public function clean_expired_transients()
    {
        global $wpdb;

        $time = time();
        $deleted = 0;

        try {
            // Get all expired transients - dengan batasan
            $expired = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM $wpdb->options 
                    WHERE option_name LIKE %s 
                    AND option_value < %d 
                    LIMIT 1000",
                    '_transient_timeout_%',
                    $time
                )
            );

            if (!empty($expired) && is_array($expired)) {
                foreach ($expired as $transient) {
                    // Ekstrak nama transient dari nama option
                    $name = str_replace('_transient_timeout_', '', $transient);

                    // Hapus transient
                    if (delete_transient($name)) {
                        $deleted++;
                    }
                }
            }

            $this->logger->log("Menghapus $deleted transient yang kedaluwarsa");
        } catch (Exception $e) {
            $this->logger->log('Error saat membersihkan transient: ' . $e->getMessage(), 'error');
        }

        return $deleted;
    }

    /**
     * Send notification email with results - dengan penanganan error yang lebih baik
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
        $message .= "Item dihapus: " . $results['removed_items'] . "\n\n";

        if (!empty($results['errors'])) {
            $message .= "-- Error --\n";
            foreach ($results['errors'] as $error) {
                $message .= "- " . $error . "\n";
            }
        }

        $message .= "\nLaporan ini dibuat secara otomatis oleh plugin WP Database Optimizer.\n";

        // Ganti wp_mail dengan fungsi PHP mail jika perlu
        $sent = @wp_mail($to, $subject, $message);

        if ($sent) {
            $this->logger->log("Notifikasi email berhasil dikirim ke $to");
        } else {
            $this->logger->log("Gagal mengirim notifikasi email ke $to", 'error');
        }
    }

    /**
     * Get option value dengan nilai default
     */
    private function get_option($key, $default = false)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return $default;
    }
}
