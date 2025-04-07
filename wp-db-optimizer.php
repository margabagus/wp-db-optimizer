<?php
/**
 * Plugin Name: WP Database Optimizer
 * Description: Optimasi database WordPress secara otomatis setiap awal bulan
 * Version: 1.0.0
 * Author: Marga Bagus 
 * Website: https://margabagus.com
 * Text Domain: wp-db-optimizer
 */

// Pastikan tidak ada akses langsung
if (!defined('ABSPATH')) {
    exit;
}

class WP_DB_Optimizer {
    
    // Singleton instance
    private static $instance = null;
    
    // Modules
    private $admin;
    private $optimizer;
    private $scheduler;
    private $logger;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Define constants
        $this->define_constants();
        
        // Load modules
        $this->load_modules();
        
        // Initialize modules
        $this->initialize_modules();
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        define('WP_DBO_VERSION', '1.0.0');
        define('WP_DBO_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WP_DBO_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WP_DBO_PLUGIN_BASENAME', plugin_basename(__FILE__));
    }
    
    /**
     * Load all modules
     */
    private function load_modules() {
        // Load module files
        require_once WP_DBO_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WP_DBO_PLUGIN_DIR . 'includes/class-optimizer.php';
        require_once WP_DBO_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once WP_DBO_PLUGIN_DIR . 'includes/class-logger.php';
    }
    
    /**
     * Initialize all modules
     */
    private function initialize_modules() {
        // Initialize each module
        $this->logger = new WP_DBO_Logger();
        $this->optimizer = new WP_DBO_Optimizer($this->logger);
        $this->scheduler = new WP_DBO_Scheduler($this->optimizer, $this->logger);
        $this->admin = new WP_DBO_Admin($this->optimizer, $this->scheduler, $this->logger);
    }
    
    /**
     * Plugin activation
     */
    public function activate_plugin() {
        // Create database tables if needed
        $this->logger->create_tables();
        
        // Schedule the optimization event
        $this->scheduler->schedule_events();
        
        // Set default options
        $this->set_default_options();
        
        // Log activation
        $this->logger->log('Plugin activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        // Clear scheduled events
        $this->scheduler->clear_scheduled_events();
        
        // Log deactivation
        $this->logger->log('Plugin deactivated');
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'optimize_tables' => true,
            'repair_tables' => true,
            'optimize_post_revisions' => true,
            'optimize_auto_drafts' => true,
            'optimize_trashed_posts' => true,
            'optimize_spam_comments' => true,
            'optimize_trashed_comments' => true,
            'optimize_expired_transients' => true,
            'notification_email' => get_option('admin_email'),
            'send_email_notification' => true,
            'keep_logs' => 30, // days
        );
        
        update_option('wp_db_optimizer_options', $default_options);
    }
}

// Initialize the plugin
function wp_db_optimizer_init() {
    return WP_DB_Optimizer::get_instance();
}

// Start the plugin
wp_db_optimizer_init();
