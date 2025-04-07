<?php

/**
 * Class WP_DBO_Admin
 * 
 * Modul untuk menangani antarmuka admin
 */
class WP_DBO_Admin
{

    private $optimizer;
    private $scheduler;
    private $logger;
    private $options;

    /**
     * Constructor
     */
    public function __construct($optimizer, $scheduler, $logger)
    {
        $this->optimizer = $optimizer;
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->options = get_option('wp_db_optimizer_options', array());

        // Tambahkan menu admin
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Tambahkan action links di halaman plugin
        add_filter('plugin_action_links_' . WP_DBO_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));

        // Tambahkan handler untuk menjalankan optimasi manual
        add_action('admin_post_run_manual_optimization', array($this, 'handle_manual_optimization'));

        // Tambahkan handler untuk membersihkan log
        add_action('admin_post_clear_optimizer_logs', array($this, 'handle_clear_logs'));

        // Tambahkan admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Tambahkan menu admin
     */
    public function add_admin_menu()
    {
        add_management_page(
            __('Database Optimizer', 'wp-db-optimizer'),
            __('DB Optimizer', 'wp-db-optimizer'),
            'manage_options',
            'wp-db-optimizer',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Daftarkan pengaturan
     */
    public function register_settings()
    {
        register_setting('wp_db_optimizer_settings', 'wp_db_optimizer_options');

        // Bagian Optimasi
        add_settings_section(
            'wp_db_optimizer_optimization_section',
            __('Pengaturan Optimasi', 'wp-db-optimizer'),
            array($this, 'render_optimization_section'),
            'wp_db_optimizer_settings'
        );

        // Pengaturan optimasi
        add_settings_field(
            'optimize_tables',
            __('Optimasi Tabel', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_tables',
                'description' => __('Optimasi semua tabel database WordPress', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'repair_tables',
            __('Perbaiki Tabel', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'repair_tables',
                'description' => __('Perbaiki tabel database yang rusak', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'optimize_post_revisions',
            __('Revisi Post', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_post_revisions',
                'description' => __('Hapus semua revisi post', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'optimize_auto_drafts',
            __('Auto Drafts', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_auto_drafts',
                'description' => __('Hapus semua auto draft', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'optimize_trashed_posts',
            __('Post di Sampah', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_trashed_posts',
                'description' => __('Hapus semua post di sampah', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'optimize_spam_comments',
            __('Komentar Spam', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_spam_comments',
                'description' => __('Hapus semua komentar spam', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'optimize_trashed_comments',
            __('Komentar di Sampah', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_trashed_comments',
                'description' => __('Hapus semua komentar di sampah', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'optimize_expired_transients',
            __('Transient Kedaluwarsa', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_optimization_section',
            array(
                'id' => 'optimize_expired_transients',
                'description' => __('Hapus semua transient yang sudah kedaluwarsa', 'wp-db-optimizer')
            )
        );

        // Bagian Notifikasi
        add_settings_section(
            'wp_db_optimizer_notification_section',
            __('Pengaturan Notifikasi', 'wp-db-optimizer'),
            array($this, 'render_notification_section'),
            'wp_db_optimizer_settings'
        );

        add_settings_field(
            'send_email_notification',
            __('Kirim Notifikasi Email', 'wp-db-optimizer'),
            array($this, 'render_checkbox_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_notification_section',
            array(
                'id' => 'send_email_notification',
                'description' => __('Kirim email notifikasi setelah optimasi selesai', 'wp-db-optimizer')
            )
        );

        add_settings_field(
            'notification_email',
            __('Email Notifikasi', 'wp-db-optimizer'),
            array($this, 'render_text_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_notification_section',
            array(
                'id' => 'notification_email',
                'description' => __('Alamat email untuk menerima notifikasi', 'wp-db-optimizer')
            )
        );

        // Bagian Log
        add_settings_section(
            'wp_db_optimizer_logs_section',
            __('Pengaturan Log', 'wp-db-optimizer'),
            array($this, 'render_logs_section'),
            'wp_db_optimizer_settings'
        );

        add_settings_field(
            'keep_logs',
            __('Simpan Log Selama', 'wp-db-optimizer'),
            array($this, 'render_number_field'),
            'wp_db_optimizer_settings',
            'wp_db_optimizer_logs_section',
            array(
                'id' => 'keep_logs',
                'description' => __('Jumlah hari untuk menyimpan log (0 untuk menyimpan selamanya)', 'wp-db-optimizer'),
                'min' => 0,
                'max' => 365,
                'step' => 1
            )
        );
    }

    /**
     * Render bagian optimasi
     */
    public function render_optimization_section()
    {
        echo '<p>' . __('Pilih tugas optimasi yang ingin dijalankan secara otomatis setiap awal bulan.', 'wp-db-optimizer') . '</p>';
    }

    /**
     * Render bagian notifikasi
     */
    public function render_notification_section()
    {
        echo '<p>' . __('Konfigurasi notifikasi email setelah optimasi selesai.', 'wp-db-optimizer') . '</p>';
    }

    /**
     * Render bagian log
     */
    public function render_logs_section()
    {
        echo '<p>' . __('Konfigurasi pengelolaan log aktivitas.', 'wp-db-optimizer') . '</p>';
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args)
    {
        $id = $args['id'];
        $description = isset($args['description']) ? $args['description'] : '';
        $value = isset($this->options[$id]) ? $this->options[$id] : false;

        echo '<label><input type="checkbox" name="wp_db_optimizer_options[' . $id . ']" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . $description . '</label>';
    }

    /**
     * Render text field
     */
    public function render_text_field($args)
    {
        $id = $args['id'];
        $description = isset($args['description']) ? $args['description'] : '';
        $value = isset($this->options[$id]) ? $this->options[$id] : '';

        echo '<input type="text" name="wp_db_optimizer_options[' . $id . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . $description . '</p>';
    }

    /**
     * Render number field
     */
    public function render_number_field($args)
    {
        $id = $args['id'];
        $description = isset($args['description']) ? $args['description'] : '';
        $value = isset($this->options[$id]) ? $this->options[$id] : '';
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : '';
        $step = isset($args['step']) ? $args['step'] : 1;

        echo '<input type="number" name="wp_db_optimizer_options[' . $id . ']" value="' . esc_attr($value) . '" class="small-text" min="' . $min . '" max="' . $max . '" step="' . $step . '" />';
        echo '<p class="description">' . $description . '</p>';
    }

    /**
     * Tambahkan action links
     */
    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('tools.php?page=wp-db-optimizer') . '">' . __('Pengaturan', 'wp-db-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Handler untuk menjalankan optimasi manual
     */
    public function handle_manual_optimization()
    {
        // Verifikasi nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'run_manual_optimization')) {
            wp_die(__('Akses ditolak', 'wp-db-optimizer'));
        }

        // Jalankan optimasi
        $results = $this->scheduler->run_manual_optimization();

        // Set transient untuk menampilkan notice
        set_transient('wp_db_optimizer_notice', array(
            'type' => 'success',
            'message' => __('Optimasi database berhasil dijalankan!', 'wp-db-optimizer')
        ), 60);

        // Redirect kembali ke halaman admin
        wp_redirect(admin_url('tools.php?page=wp-db-optimizer&tab=logs'));
        exit;
    }

    /**
     * Handler untuk membersihkan log
     */
    public function handle_clear_logs()
    {
        // Verifikasi nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'clear_optimizer_logs')) {
            wp_die(__('Akses ditolak', 'wp-db-optimizer'));
        }

        // Bersihkan log
        $this->logger->delete_all_logs();

        // Set transient untuk menampilkan notice
        set_transient('wp_db_optimizer_notice', array(
            'type' => 'success',
            'message' => __('Semua log berhasil dihapus!', 'wp-db-optimizer')
        ), 60);

        // Redirect kembali ke halaman admin
        wp_redirect(admin_url('tools.php?page=wp-db-optimizer&tab=logs'));
        exit;
    }

    /**
     * Tampilkan admin notices
     */
    public function admin_notices()
    {
        $notice = get_transient('wp_db_optimizer_notice');

        if ($notice) {
            $class = 'notice notice-' . $notice['type'] . ' is-dismissible';
            $message = $notice['message'];

            echo '<div class="' . $class . '"><p>' . $message . '</p></div>';

            delete_transient('wp_db_optimizer_notice');
        }
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
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';

?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-db-optimizer&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Pengaturan', 'wp-db-optimizer'); ?>
                </a>
                <a href="?page=wp-db-optimizer&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Log', 'wp-db-optimizer'); ?>
                </a>
                <a href="?page=wp-db-optimizer&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Status', 'wp-db-optimizer'); ?>
                </a>
            </h2>

            <div class="tab-content">
                <?php
                if ($active_tab == 'settings') {
                    $this->render_settings_tab();
                } elseif ($active_tab == 'logs') {
                    $this->render_logs_tab();
                } elseif ($active_tab == 'status') {
                    $this->render_status_tab();
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render tab pengaturan
     */
    private function render_settings_tab()
    {
    ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_db_optimizer_settings');
            do_settings_sections('wp_db_optimizer_settings');
            submit_button(__('Simpan Pengaturan', 'wp-db-optimizer'));
            ?>
        </form>
    <?php
    }

    /**
     * Render tab log
     */
    private function render_logs_tab()
    {
        // Dapatkan log terakhir
        $logs = $this->logger->get_logs();
        $log_count = $this->logger->get_log_count();

    ?>
        <div class="log-actions">
            <h3><?php _e('Log Aktivitas', 'wp-db-optimizer'); ?></h3>

            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=run_manual_optimization'), 'run_manual_optimization'); ?>" class="button button-primary">
                    <?php _e('Jalankan Optimasi Sekarang', 'wp-db-optimizer'); ?>
                </a>

                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=clear_optimizer_logs'), 'clear_optimizer_logs'); ?>" class="button" onclick="return confirm('<?php _e('Apakah Anda yakin ingin menghapus semua log?', 'wp-db-optimizer'); ?>');">
                    <?php _e('Hapus Semua Log', 'wp-db-optimizer'); ?>
                </a>
            </p>

            <?php if (empty($logs)) : ?>
                <p><?php _e('Belum ada log aktivitas.', 'wp-db-optimizer'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Waktu', 'wp-db-optimizer'); ?></th>
                            <th><?php _e('Tipe', 'wp-db-optimizer'); ?></th>
                            <th><?php _e('Pesan', 'wp-db-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log->log_time); ?></td>
                                <td>
                                    <?php
                                    $type_class = 'info';
                                    if ($log->log_type == 'error') {
                                        $type_class = 'error';
                                    } elseif ($log->log_type == 'warning') {
                                        $type_class = 'warning';
                                    }
                                    echo '<span class="log-type log-type-' . esc_attr($type_class) . '">' . esc_html($log->log_type) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo esc_html($log->log_message); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <style>
                    .log-type {
                        display: inline-block;
                        padding: 2px 8px;
                        border-radius: 3px;
                        font-size: 12px;
                        text-transform: uppercase;
                    }

                    .log-type-info {
                        background-color: #e7f0f5;
                        color: #0073aa;
                    }

                    .log-type-error {
                        background-color: #f8d7da;
                        color: #d63638;
                    }

                    .log-type-warning {
                        background-color: #fff8e5;
                        color: #b45900;
                    }
                </style>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render tab status
     */
    private function render_status_tab()
    {
        global $wpdb;

        // Dapatkan informasi database
        $db_size = $this->get_database_size();
        $total_tables = count($this->get_database_tables());

        // Dapatkan waktu optimasi berikutnya
        $next_optimization = $this->scheduler->get_next_scheduled_time();

    ?>
        <div class="status-info">
            <h3><?php _e('Informasi Database', 'wp-db-optimizer'); ?></h3>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php _e('Ukuran Database', 'wp-db-optimizer'); ?></th>
                        <td><?php echo esc_html($this->format_size($db_size)); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Jumlah Tabel', 'wp-db-optimizer'); ?></th>
                        <td><?php echo esc_html($total_tables); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Nama Database', 'wp-db-optimizer'); ?></th>
                        <td><?php echo esc_html(DB_NAME); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Host Database', 'wp-db-optimizer'); ?></th>
                        <td><?php echo esc_html(DB_HOST); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Versi MySQL', 'wp-db-optimizer'); ?></th>
                        <td><?php echo esc_html($wpdb->db_version()); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php _e('Status Optimasi', 'wp-db-optimizer'); ?></h3>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php _e('Optimasi Berikutnya', 'wp-db-optimizer'); ?></th>
                        <td>
                            <?php
                            if ($next_optimization) {
                                echo esc_html(date_i18n('Y-m-d H:i:s', $next_optimization));
                                echo ' (' . esc_html(human_time_diff($next_optimization)) . ')';
                            } else {
                                _e('Tidak dijadwalkan', 'wp-db-optimizer');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Versi Plugin', 'wp-db-optimizer'); ?></th>
                        <td><?php echo esc_html(WP_DBO_VERSION); ?></td>
                    </tr>
                </tbody>
            </table>

            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=run_manual_optimization'), 'run_manual_optimization'); ?>" class="button button-primary">
                    <?php _e('Jalankan Optimasi Sekarang', 'wp-db-optimizer'); ?>
                </a>
            </p>
        </div>
<?php
    }

    /**
     * Dapatkan ukuran database
     */
    private function get_database_size()
    {
        global $wpdb;

        $sql = "SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = %s";

        $size = $wpdb->get_var($wpdb->prepare($sql, DB_NAME));

        return $size ? $size : 0;
    }

    /**
     * Dapatkan daftar tabel database
     */
    private function get_database_tables()
    {
        global $wpdb;

        $sql = "SHOW TABLES LIKE %s";

        $tables = $wpdb->get_results($wpdb->prepare($sql, $wpdb->prefix . '%'), ARRAY_N);

        return $tables ? $tables : array();
    }

    /**
     * Format ukuran file
     */
    private function format_size($size)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $size = max($size, 0);
        $pow = floor(($size ? log($size) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $size /= pow(1024, $pow);

        return round($size, 2) . ' ' . $units[$pow];
    }
}
