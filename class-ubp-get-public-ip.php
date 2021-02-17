<?php

/**
 * Work around local DNS by asking public web sites to look up the IP of a domain
 */
class UBP_Get_Public_IP {

	public $domain;
	public $transient;
	public $expire = 86400;
	public $ip;
	public $ip_pattern = '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/';

	public function __construct( $domain ) {
		$this->domain    = $domain;
		$this->transient = 'dbp_' . $domain;

		// Filter to set remote IP programmatically
		$ip = apply_filters( 'ubp_remote_ip', false );
		if ( empty( $ip ) ) {
			$ip = get_transient( $this->transient );
		}

		if ( empty( $ip ) ) {
			// Loading and serving from localhost
			$custom_url  = apply_filters( 'ubp_ip_url', false, $this->domain );
			$custom_args = apply_filters( 'ubp_ip_args', array() );

			if ( $custom_url ) {
				$ip = $this->get_ip( $custom_url, $custom_args );
			}
			if ( ! $ip ) {
				$ip = $this->get_ip( "http://aruljohn.com/cgi-bin/hostname2ip.pl?host=$domain", array( 'referer' => 'http://aruljohn.com/hostname2ip.html' ) );
			}

			if ( $ip ) {
				set_transient( $this->transient, $ip, $this->expire );
				$this->ip = $ip;
			}
		}

		if ( empty( $ip ) ) {
			$ip = $domain;
		}

		$this->ip = $ip;
	}

	public function __toString() {
		return $this->ip;
	}

	public function get_ip( $url, $args = array() ) {
		$defaults = array(
			'method'  => 'GET',
			'referer' => $domain,
			'body'    => '',
			'index'   => 0,
		);
		$args     = wp_parse_args( $args, $defaults );
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$query_args = array(
			'method'  => $method,
			'headers' => array( 'Referer' => $referer ),
			'body'    => $body,
		);

		$response = wp_remote_get( $url, $query_args );

		if ( ! is_wp_error( $response ) ) {
			$body = wp_strip_all_tags( $response['body'] );

			preg_match_all( $this->ip_pattern, $body, $matches );

			return ! empty( $matches[0][ $index ] )
				? $matches[0][ $index ]
				: false;
		}

		return false;
	}
}
