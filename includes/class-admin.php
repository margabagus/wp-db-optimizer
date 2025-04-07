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

        // Tambahkan handler untuk menjalankan optimasi manual - dengan penanganan error lebih baik
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
     * Handler untuk menjalankan optimasi manual dengan penanganan error yang lebih baik
     */
    public function handle_manual_optimization()
    {
        // Verifikasi nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'run_manual_optimization')) {
            wp_die(__('Akses ditolak', 'wp-db-optimizer'));
        }

        try {
            // Set batas waktu eksekusi yang lebih lama jika memungkinkan
            @set_time_limit(300);

            // Jalankan optimasi
            $results = $this->scheduler->run_manual_optimization();

            // Set pesan sukses
            if ($results['status'] === 'success') {
                set_transient('wp_db_optimizer_notice', array(
                    'type' => 'success',
                    'message' => sprintf(
                        __('Optimasi database berhasil! %d tabel dioptimasi, %d item dihapus dalam %s detik.', 'wp-db-optimizer'),
                        $results['optimized_tables'],
                        $results['removed_items'],
                        $results['execution_time']
                    )
                ), 60);
            } else {
                // Set pesan error jika gagal
                $error_msg = __('Optimasi database selesai dengan error.', 'wp-db-optimizer');
                if (!empty($results['errors'])) {
                    $error_msg .= ' ' . implode(' ', $results['errors']);
                }

                set_transient('wp_db_optimizer_notice', array(
                    'type' => 'error',
                    'message' => $error_msg
                ), 60);
            }
        } catch (Exception $e) {
            // Tangkap semua error dan tampilkan pesan yang informatif
            $this->logger->log('Error saat menjalankan optimasi manual: ' . $e->getMessage(), 'error');

            set_transient('wp_db_optimizer_notice', array(
                'type' => 'error',
                'message' => __('Error saat menjalankan optimasi: ', 'wp-db-optimizer') . $e->getMessage()
            ), 60);
        }

        // Redirect kembali ke halaman admin
        wp_safe_redirect(admin_url('tools.php?page=wp-db-optimizer&tab=logs'));
        exit;
    }

    // Sisanya dari kode tetap sama...

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
     * Handler untuk membersihkan log
     */
    public function handle_clear_logs()
    {
        // Verifikasi nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'clear_optimizer_logs')) {
            wp_die(__('Akses ditolak', 'wp-db-optimizer'));
        }

        try {
            // Bersihkan log
            $this->logger->delete_all_logs();

            // Set transient untuk menampilkan notice
            set_transient('wp_db_optimizer_notice', array(
                'type' => 'success',
                'message' => __('Semua log berhasil dihapus!', 'wp-db-optimizer')
            ), 60);
        } catch (Exception $e) {
            // Tangkap error
            set_transient('wp_db_optimizer_notice', array(
                'type' => 'error',
                'message' => __('Error saat menghapus log: ', 'wp-db-optimizer') . $e->getMessage()
            ), 60);
        }

        // Redirect kembali ke halaman admin
        wp_safe_redirect(admin_url('tools.php?page=wp-db-optimizer&tab=logs'));
        exit;
    }

    // Sisanya dari kode tetap sama...

    /**
     * Render optimization section
     */
    public function render_optimization_section()
    {
        echo '<p>' . __('Pilih tugas optimasi yang ingin dijalankan secara otomatis setiap awal bulan.', 'wp-db-optimizer') . '</p>';
    }

    /**
     * Render notification section
     */
    public function render_notification_section()
    {
        echo '<p>' . __('Konfigurasi notifikasi email setelah optimasi selesai.', 'wp-db-optimizer') . '</p>';
    }

    /**
     * Render logs section
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
     * Add action links
     */
    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('tools.php?page=wp-db-optimizer') . '">' . __('Pengaturan', 'wp-db-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Display admin notices
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
     * Render admin page
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

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

    // Sisanya dari kode tetap sama...
}
