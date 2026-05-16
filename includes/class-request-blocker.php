<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WRB_Request_Blocker {

    private static $instance  = null;
    private string $option_key = 'wrb_blocked_domains';

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // لایه ۱: بلاک PHP HTTP requests
        add_filter( 'pre_http_request', [ $this, 'maybe_block' ], 5, 3 );

        // لایه ۲: حذف asset های enqueue شده
        add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_blocked_assets' ], 999 );
        add_action( 'wp_print_styles', [ $this, 'dequeue_blocked_assets' ], 999 );
        add_action( 'wp_print_scripts', [ $this, 'dequeue_blocked_assets' ], 999 );

        // داشبورد
        add_action( 'admin_enqueue_scripts', [ $this, 'dequeue_blocked_assets' ], 999 );
        add_action( 'admin_print_styles', [ $this, 'dequeue_blocked_assets' ], 999 );
        add_action( 'admin_print_scripts', [ $this, 'dequeue_blocked_assets' ], 999 );

        // المنتور
//        add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'dequeue_blocked_assets' ], 999 );
//        add_action( 'elementor/preview/enqueue_styles', [ $this, 'dequeue_blocked_assets' ], 999 );
//        add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'dequeue_blocked_assets' ], 999 );

        // لایه ۳: حذف مستقیم از HTML
        add_action( 'template_redirect', [ $this, 'start_output_buffer' ], 0 );
        add_action( 'admin_init', [ $this, 'start_output_buffer' ], 0 );
        add_action( 'login_init', [ $this, 'start_output_buffer' ], 0 );

        // آواتار
        add_filter( 'get_avatar_url', [ $this, 'maybe_block_avatar' ], 10, 3 );
    }

    public function start_output_buffer(): void {
        ob_start( [ $this, 'filter_html_output' ] );
    }
    public function filter_html_output( string $html ): string {
        $blocked = $this->get_blocked();
        if ( empty( $blocked ) ) return $html;

        foreach ( $blocked as $domain ) {
            $domain_escaped = preg_quote( $domain, '/' );
            $replacement    = '<!-- WRB blocked: ' . esc_html( $domain ) . ' -->';

            // 1) حذف <link>‌هایی که به این دامنه اشاره دارند
            $html = preg_replace(
                '/<link\b[^>]*\bhref=["\'][^"\']*' . $domain_escaped . '[^"\']*["\'][^>]*\/?>/i',
                $replacement,
                $html
            );

            // 2) حذف <script>‌هایی که به این دامنه اشاره دارند
            $html = preg_replace(
                '/<script\b[^>]*\bsrc=["\'][^"\']*' . $domain_escaped . '[^"\']*["\'][^"\']*["\'][^>]*>.*?<\/script>/is',
                $replacement,
                $html
            );

            // 3) حذف <img>‌هایی که به این دامنه اشاره دارند (برای Gravatar و سایر تصاویر خارجی)
            $html = preg_replace(
                '/<img\b[^>]*\bsrc=["\'][^"\']*' . $domain_escaped . '[^"\']*["\'][^>]*\/?>/i',
                $replacement,
                $html
            );

            // 4) حذف @importهای داخل <style> که به این دامنه اشاره دارند
            $html = preg_replace(
                '/@import\s+url\(["\']?[^"\')]*' . $domain_escaped . '[^"\')]*["\']?\)\s*;?/i',
                '/* WRB blocked import: ' . esc_html( $domain ) . ' */',
                $html
            );
        }
        return $html;
    }
    public function dequeue_blocked_assets(): void {
        $blocked = $this->get_blocked();
        if ( empty( $blocked ) ) return;

        // بررسی همه استایل‌های enqueue شده
        global $wp_styles, $wp_scripts;

        foreach ( [ $wp_styles, $wp_scripts ] as $queue ) {
            if ( ! $queue ) continue;
            foreach ( $queue->registered as $handle => $dep ) {
                if ( empty( $dep->src ) ) continue;
                $host = parse_url( $dep->src, PHP_URL_HOST ) ?: '';
                if ( $host && in_array( $host, $blocked, true ) ) {
                    $queue->dequeue( $handle );
                    $queue->remove( $handle );
                }
            }
        }
    }
    public function maybe_block( $preempt, array $args, string $url ) {
        $domain = parse_url( $url, PHP_URL_HOST ) ?: '';
        if ( $domain && in_array( $domain, $this->get_blocked(), true ) ) {
            return new WP_Error(
                'wrb_blocked',
                sprintf( 'WP Request Blocker: درخواست به «%s» مسدود شد.', $domain )
            );
        }
        return $preempt;
    }
    public function maybe_block_avatar( $url, $id_or_email = null, $args = [] ){

        $blocked = get_option( 'wrb_blocked_domains', [] );

        $host = parse_url( $url, PHP_URL_HOST );

        if ( $host && in_array( $host, $blocked, true ) ) {
            return '';
        }

        return $url;
    }
    public function get_blocked(): array {
        return get_option( $this->option_key, [] );
    }

    public function add( string $domain ): bool {
        $domain  = strtolower( trim( $domain ) );
        $blocked = $this->get_blocked();
        if ( $domain && ! in_array( $domain, $blocked, true ) ) {
            $blocked[] = $domain;
            update_option( $this->option_key, $blocked, false );
            return true;
        }
        return false;
    }

    public function remove( string $domain ): void {
        $blocked = array_values( array_diff( $this->get_blocked(), [ $domain ] ) );
        update_option( $this->option_key, $blocked, false );
    }

    public function clear(): void {
        delete_option( $this->option_key );
    }

}
