<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WRB_Request_Monitor {

    private static $instance   = null;
    private int    $timeout     = 10;
    private string $option_key  = 'wrb_timeout_log';

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'http_request_args', [ $this, 'inject_start_time' ], 10, 2 );
        add_action( 'http_api_debug',    [ $this, 'capture_response' ],  10, 5 );
    }

    /** تزریق زمان شروع به args */
    public function inject_start_time( array $args, string $url ): array {
        $args['timeout']      = $this->timeout;
        $args['_wrb_start']   = microtime( true );
        return $args;
    }

    /** ثبت درخواست‌های کند / تایم‌اوت */
    public function capture_response( $response, $context, $class, $args, $url ): void {
        if ( empty( $args['_wrb_start'] ) ) return;

        $duration   = microtime( true ) - $args['_wrb_start'];
        $is_timeout = is_wp_error( $response )
            && str_contains( $response->get_error_message(), 'timed out' );

        if ( ! $is_timeout && $duration < $this->timeout ) return;

        $this->record( $url, $duration, $is_timeout );
    }

    private function record( string $url, float $duration, bool $is_timeout ): void {
        $log    = $this->get_log();
        $domain = parse_url( $url, PHP_URL_HOST ) ?: $url;

        if ( ! isset( $log[ $domain ] ) ) {
            $log[ $domain ] = [
                'domain'       => $domain,
                'count'        => 0,
                'total_time'   => 0.0,
                'last_seen'    => 0,
                'urls'         => [],
                'is_timeout'   => false,
            ];
        }

        $entry = &$log[ $domain ];
        $entry['count']++;
        $entry['total_time'] += $duration;
        $entry['last_seen']   = time();
        $entry['is_timeout']  = $entry['is_timeout'] || $is_timeout;

        if ( count( $entry['urls'] ) < 5 && ! in_array( $url, $entry['urls'], true ) ) {
            $entry['urls'][] = $url;
        }

        update_option( $this->option_key, $log, false );
    }

    public function get_log(): array {
        return get_option( $this->option_key, [] );
    }

    public function clear_log(): void {
        delete_option( $this->option_key );
    }
}
