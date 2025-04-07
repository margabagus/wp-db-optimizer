<?php

/**
 * Class WP_DBO_Scheduler
 * 
 * Modul untuk menangani penjadwalan tugas optimasi
 */
class WP_DBO_Scheduler
{

    private $optimizer;
    private $logger;

    /**
     * Constructor
     */
    public function __construct($optimizer, $logger)
    {
        $this->optimizer = $optimizer;
        $this->logger = $logger;

        // Hook untuk menjalankan optimasi
        add_action('wp_db_optimizer_monthly_event', array($this, 'run_scheduled_optimization'));

        // Custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }

    /**
     * Tambahkan jadwal cron kustom
     */
    public function add_cron_schedules($schedules)
    {
        // Tambahkan jadwal "awal bulan"
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Setiap Bulan', 'wp-db-optimizer')
        );

        return $schedules;
    }

    /**
     * Jadwalkan event optimasi
     */
    public function schedule_events()
    {
        if (!wp_next_scheduled('wp_db_optimizer_monthly_event')) {
            // Jadwalkan untuk pukul 01:00 pada tanggal 1 bulan berikutnya
            $next_month = strtotime('first day of next month 01:00:00');
            wp_schedule_event($next_month, 'monthly', 'wp_db_optimizer_monthly_event');

            $this->logger->log('Optimasi database dijadwalkan untuk ' . date('Y-m-d H:i:s', $next_month));
        }
    }

    /**
     * Hapus semua event terjadwal
     */
    public function clear_scheduled_events()
    {
        wp_clear_scheduled_hook('wp_db_optimizer_monthly_event');
        $this->logger->log('Event optimasi database terjadwal telah dihapus');
    }

    /**
     * Jalankan optimasi terjadwal
     */
    public function run_scheduled_optimization()
    {
        $this->logger->log('Memulai optimasi database terjadwal');

        // Jalankan semua optimasi
        $results = $this->optimizer->run_all_optimizations();

        if ($results['status'] === 'success') {
            $this->logger->log('Optimasi database terjadwal berhasil diselesaikan');
        } else {
            $this->logger->log('Optimasi database terjadwal selesai dengan error', 'error');
        }

        return $results;
    }

    /**
     * Jalankan optimasi secara manual
     */
    public function run_manual_optimization()
    {
        $this->logger->log('Memulai optimasi database manual');

        // Jalankan semua optimasi
        $results = $this->optimizer->run_all_optimizations();

        if ($results['status'] === 'success') {
            $this->logger->log('Optimasi database manual berhasil diselesaikan');
        } else {
            $this->logger->log('Optimasi database manual selesai dengan error', 'error');
        }

        return $results;
    }

    /**
     * Dapatkan waktu optimasi berikutnya
     */
    public function get_next_scheduled_time()
    {
        $next_scheduled = wp_next_scheduled('wp_db_optimizer_monthly_event');

        if ($next_scheduled) {
            return $next_scheduled;
        }

        return false;
    }
}
