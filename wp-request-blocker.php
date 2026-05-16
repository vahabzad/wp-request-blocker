<?php
/**
 * Plugin Name:       WP Request Blocker
 * Plugin URI:        https://vahabzad.ir
 * Description:       شناسایی و بلاک کردن درخواست‌های تایم‌اوت برای افزایش سرعت سایت
 * Version:           2.1.5
 * Author:            سید حمید وهاب زاد
 * Author URI:        https://vahabzad.ir
 * Text Domain:       wp-request-blocker
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
define( 'WRB_VERSION',  '2.1.9' );
define( 'WRB_PATH',     plugin_dir_path( __FILE__ ) );
define( 'WRB_URL',      plugin_dir_url( __FILE__ ) );
define( 'WRB_BASENAME', plugin_basename( __FILE__ ) );

require_once WRB_PATH . 'includes/class-request-monitor.php';
require_once WRB_PATH . 'includes/class-request-blocker.php';
require_once WRB_PATH . 'includes/class-admin-page.php';
//require_once WRB_PATH . 'class-debug-monitor.php';
//require_once WRB_PATH . 'class-debug-admin-page.php';

final class WP_Request_Blocker_Plugin {

    private static $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_filter( 'plugin_action_links_' . WRB_BASENAME, [ $this, 'add_settings_link' ] );

    }

    public function init(): void {
        WRB_Request_Monitor::get_instance();
        WRB_Request_Blocker::get_instance();

        if ( is_admin() ) {
            WRB_Admin_Page::get_instance();
        }
    }

    /**
     * دکمه «تنظیمات» در لیست افزونه‌ها — لینک به داشبورد اصلی
     */
    public function add_settings_link( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=wrb-dashboard' ) ),
            'تنظیمات'
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

}

WP_Request_Blocker_Plugin::get_instance();