<?php

/**
 * Plugin Name: WP Database Optimizer
 * Description: Optimasi database WordPress secara otomatis setiap awal bulan
 * Version: 1.0.0
 * Author: Marga Bagus
 * Website: https://github.com/margabagus/wp-db-optimizer
 * Website Author: https://margabagus.com
 * License: GPL2
 * License URL: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-db-optimizer
 */

// Pastikan tidak ada akses langsung
if (!defined('ABSPATH')) {
    exit;
}

class WP_DB_Optimizer
{

    // Singleton instance
    private static $instance = null;
    private $options;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Dapatkan opsi plugin
        $this->options = get_option('wp_db_optimizer_options', array(
            'send_email' => true,
            'notification_email' => get_option('admin_email')
        ));

        // Tambahkan menu admin
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Jadwalkan event untuk optimasi bulanan
        add_action('wp_db_optimizer_monthly_event', array($this, 'run_optimization'));

        // Tambahkan jadwal kustom
        add_filter('cron_schedules', array($this, 'add_monthly_schedule'));

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

        // Tambahkan handler untuk optimasi manual
        add_action('admin_post_run_manual_optimization', array($this, 'handle_manual_optimization'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Tambahkan menu admin
     */
    public function add_admin_menu()
    {
        add_management_page(
            'Database Optimizer',
            'DB Optimizer',
            'manage_options',
            'wp-db-optimizer',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting('wp_db_optimizer_settings', 'wp_db_optimizer_options');
    }

    /**
     * Render halaman admin
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Dapatkan tab aktif
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'status';

        // Tampilkan pesan sukses jika ada
        if (isset($_GET['optimized']) && $_GET['optimized'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Optimasi database berhasil dilakukan!</p></div>';
        }

        // Dapatkan info database
        global $wpdb;
        $db_name = DB_NAME;
        $db_size = $this->get_database_size();
        $tables = $this->get_tables_info();
        $next_run = wp_next_scheduled('wp_db_optimizer_monthly_event');

?>
        <div class="wrap">
            <h1>Database Optimizer</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-db-optimizer&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>">Status</a>
                <a href="?page=wp-db-optimizer&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Pengaturan</a>
            </h2>

            <?php if ($active_tab == 'status') : ?>
                <div class="card">
                    <h2>Informasi Database</h2>
                    <p><strong>Nama Database:</strong> <?php echo esc_html($db_name); ?></p>
                    <p><strong>Ukuran Database:</strong> <?php echo esc_html($this->format_size($db_size)); ?></p>
                    <p><strong>Jumlah Tabel:</strong> <?php echo count($tables); ?></p>
                    <p><strong>Optimasi Berikutnya:</strong>
                        <?php
                        if ($next_run) {
                            echo date_i18n('Y-m-d H:i:s', $next_run);
                            echo ' (' . human_time_diff($next_run) . ' lagi)';
                        } else {
                            echo 'Tidak dijadwalkan';
                        }
                        ?>
                    </p>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Optimasi Manual</h2>
                    <p>Klik tombol di bawah untuk menjalankan optimasi database secara manual:</p>
                    <p>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=run_manual_optimization'), 'run_manual_optimization'); ?>" class="button button-primary">
                            Jalankan Optimasi Sekarang
                        </a>
                    </p>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Daftar Tabel Database</h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Nama Tabel</th>
                                <th>Ukuran</th>
                                <th>Baris</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table) : ?>
                                <tr>
                                    <td><?php echo esc_html($table['name']); ?></td>
                                    <td><?php echo esc_html($this->format_size($table['size'])); ?></td>
                                    <td><?php echo esc_html($table['rows']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($active_tab == 'settings') : ?>
                <form method="post" action="options.php">
                    <?php settings_fields('wp_db_optimizer_settings'); ?>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Notifikasi Email</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wp_db_optimizer_options[send_email]" value="1" <?php checked(isset($this->options['send_email']) ? $this->options['send_email'] : false); ?> />
                                    Kirim email notifikasi setelah optimasi selesai
                                </label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Alamat Email</th>
                            <td>
                                <input type="email" name="wp_db_optimizer_options[notification_email]" value="<?php echo esc_attr(isset($this->options['notification_email']) ? $this->options['notification_email'] : get_option('admin_email')); ?>" class="regular-text" />
                                <p class="description">Alamat email untuk menerima notifikasi hasil optimasi.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Simpan Pengaturan'); ?>
                </form>
            <?php endif; ?>
        </div>
<?php
    }

    /**
     * Tambahkan jadwal bulanan
     */
    public function add_monthly_schedule($schedules)
    {
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => 'Setiap Bulan'
        );
        return $schedules;
    }

    /**
     * Aktivasi plugin
     */
    public function activate_plugin()
    {
        // Jadwalkan event
        if (!wp_next_scheduled('wp_db_optimizer_monthly_event')) {
            $next_month = strtotime('first day of next month 01:00:00');
            wp_schedule_event($next_month, 'monthly', 'wp_db_optimizer_monthly_event');
        }

        // Buat tabel log jika belum ada
        $this->create_log_table();
    }

    /**
     * Deaktivasi plugin
     */
    public function deactivate_plugin()
    {
        // Hapus jadwal
        wp_clear_scheduled_hook('wp_db_optimizer_monthly_event');
    }

    /**
     * Handle optimasi manual
     */
    public function handle_manual_optimization()
    {
        // Verifikasi nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'run_manual_optimization')) {
            wp_die('Akses ditolak');
        }

        // Jalankan optimasi
        $this->run_optimization(true);

        // Redirect kembali
        wp_redirect(admin_url('tools.php?page=wp-db-optimizer&optimized=1'));
        exit;
    }

    /**
     * Jalankan optimasi database
     */
    public function run_optimization($is_manual = false)
    {
        global $wpdb;

        // Set time limit yang lebih lama
        @set_time_limit(300);

        // Log awal optimasi
        $log_id = $this->log('Memulai proses optimasi database ' . ($is_manual ? 'manual' : 'terjadwal'));

        $start_time = microtime(true);
        $results = array(
            'optimized_tables' => 0,
            'removed_items' => 0
        );

        // 1. Optimasi tabel
        $tables = $this->get_wp_tables();
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE $table");
            if ($result !== false) {
                $results['optimized_tables']++;
                $this->log("Tabel {$table} berhasil dioptimasi");
            }
        }

        // 2. Bersihkan post revisions dengan cara aman
        $deleted = $wpdb->query("DELETE FROM $wpdb->posts WHERE post_type = 'revision' LIMIT 500");
        if ($deleted !== false) {
            $results['removed_items'] += $deleted;
            $this->log("Menghapus {$deleted} revisi post");
        }

        // 3. Bersihkan auto drafts
        $deleted = $wpdb->query("DELETE FROM $wpdb->posts WHERE post_status = 'auto-draft' LIMIT 100");
        if ($deleted !== false) {
            $results['removed_items'] += $deleted;
            $this->log("Menghapus {$deleted} auto draft");
        }

        // 4. Bersihkan trashed posts
        $deleted = $wpdb->query("DELETE FROM $wpdb->posts WHERE post_status = 'trash' LIMIT 100");
        if ($deleted !== false) {
            $results['removed_items'] += $deleted;
            $this->log("Menghapus {$deleted} post di sampah");
        }

        // 5. Bersihkan spam dan trashed comments
        $deleted = $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'spam' LIMIT 100");
        if ($deleted !== false) {
            $results['removed_items'] += $deleted;
            $this->log("Menghapus {$deleted} komentar spam");
        }

        $deleted = $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'trash' LIMIT 100");
        if ($deleted !== false) {
            $results['removed_items'] += $deleted;
            $this->log("Menghapus {$deleted} komentar di sampah");
        }

        // 6. Bersihkan transient kedaluwarsa
        $time = time();
        $expired_transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options 
                WHERE option_name LIKE %s 
                AND option_value < %d 
                LIMIT 100",
                $wpdb->esc_like('_transient_timeout_') . '%',
                $time
            )
        );

        $deleted_transients = 0;
        if (is_array($expired_transients) && !empty($expired_transients)) {
            foreach ($expired_transients as $transient) {
                $name = str_replace('_transient_timeout_', '', $transient);
                if (delete_transient($name)) {
                    $deleted_transients++;
                }
            }

            $results['removed_items'] += $deleted_transients;
            $this->log("Menghapus {$deleted_transients} transient yang kedaluwarsa");
        }

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        // Log hasil optimasi
        $this->log("Optimasi selesai dalam {$execution_time} detik. {$results['optimized_tables']} tabel dioptimasi, {$results['removed_items']} item dihapus.");

        // Kirim email notifikasi jika diaktifkan
        if (isset($this->options['send_email']) && $this->options['send_email']) {
            $this->send_notification_email($results, $execution_time);
        }

        return $results;
    }

    /**
     * Kirim email notifikasi
     */
    private function send_notification_email($results, $execution_time)
    {
        $to = isset($this->options['notification_email']) ? $this->options['notification_email'] : get_option('admin_email');
        $subject = 'Laporan Optimasi Database WordPress - ' . get_bloginfo('name');

        $message = "=== LAPORAN OPTIMASI DATABASE ===\n\n";
        $message .= "Situs: " . get_bloginfo('name') . " (" . get_site_url() . ")\n";
        $message .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Durasi: " . $execution_time . " detik\n\n";

        $message .= "--- HASIL OPTIMASI ---\n";
        $message .= "Tabel dioptimasi: " . $results['optimized_tables'] . "\n";
        $message .= "Item dihapus: " . $results['removed_items'] . "\n\n";

        $message .= "--- INFORMASI DATABASE ---\n";
        $message .= "Nama Database: " . DB_NAME . "\n";
        $message .= "Ukuran Database: " . $this->format_size($this->get_database_size()) . "\n";
        $message .= "Jumlah Tabel: " . count($this->get_wp_tables()) . "\n\n";

        $message .= "Email ini dikirim secara otomatis oleh plugin WP Database Optimizer.\n";

        // Kirim email
        $sent = wp_mail($to, $subject, $message);

        if ($sent) {
            $this->log("Email notifikasi berhasil dikirim ke {$to}");
        } else {
            $this->log("Gagal mengirim email notifikasi ke {$to}", 'error');
        }
    }

    /**
     * Create log table
     */
    private function create_log_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'db_optimizer_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log_message text NOT NULL,
            log_type varchar(20) NOT NULL DEFAULT 'info',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log message to database
     */
    private function log($message, $type = 'info')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'db_optimizer_logs';

        $data = array(
            'log_message' => $message,
            'log_type' => $type
        );

        $wpdb->insert($table_name, $data);

        return $wpdb->insert_id;
    }

    /**
     * Dapatkan semua tabel WP
     */
    private function get_wp_tables()
    {
        global $wpdb;

        $tables = array();
        $result = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);

        if (is_array($result)) {
            foreach ($result as $table) {
                $tables[] = $table[0];
            }
        }

        return $tables;
    }

    /**
     * Dapatkan info tabel
     */
    private function get_tables_info()
    {
        global $wpdb;

        $tables = array();
        $db_name = DB_NAME;

        $result = $wpdb->get_results(
            "SELECT 
                TABLE_NAME AS 'name',
                TABLE_ROWS AS 'rows',
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'size'
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = '$db_name'
            AND TABLE_NAME LIKE '{$wpdb->prefix}%'
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC"
        );

        if (is_array($result)) {
            foreach ($result as $table) {
                $tables[] = array(
                    'name' => $table->name,
                    'rows' => $table->rows,
                    'size' => $table->size * 1024 * 1024 // Convert back to bytes
                );
            }
        }

        return $tables;
    }

    /**
     * Dapatkan ukuran database
     */
    private function get_database_size()
    {
        global $wpdb;

        $size = 0;
        $db_name = DB_NAME;

        $result = $wpdb->get_row(
            "SELECT SUM(data_length + index_length) AS size
            FROM information_schema.TABLES
            WHERE table_schema = '$db_name'"
        );

        if ($result) {
            $size = $result->size;
        }

        return $size;
    }

    /**
     * Format ukuran
     */
    private function format_size($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Inisialisasi plugin
function wp_db_optimizer_init()
{
    return WP_DB_Optimizer::get_instance();
}
add_action('plugins_loaded', 'wp_db_optimizer_init');

// Pastikan plugin terinisialisasi
wp_db_optimizer_init();
