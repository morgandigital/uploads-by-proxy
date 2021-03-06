<?php

/**
 * Handle redirection through WordPress 404 Template
 */
class UBP_404_Template {

	public $siteurl;
	public $scheme;
	public $domain;
	public $remote_path;
	public $local_path;
	public $response;

	public function __construct() {
		// Only run for whitelisted paths
		if ( ! $this->allow_path() ) {
			return;
		}

		$this->stream();
	}

	/**
	 * Stream files through PHP
	 */
	public function stream() {
		$url = $this->get_scheme() . '://' . $this->get_auth() . $this->get_domain() . $this->get_remote_path();

		$this->response = wp_remote_get( $url );

		if ( ! is_wp_error( $this->response ) && 200 === $this->response['response']['code'] ) {
			$this->download();
		}
	}

	public function download() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;

		WP_Filesystem();

		$u = wp_upload_dir();

		$basedir = str_replace( $this->uploads_basedir(), '', $u['basedir'] );
		$abspath = $basedir . $this->get_local_path();
		$dir     = dirname( $abspath );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			$this->display_and_exit( "Please check permissions. Could not create directory $dir" );
		}

		$saved_image = $wp_filesystem->put_contents( $abspath, $this->response['body'], FS_CHMOD_FILE ); // predefined mode settings for WP files

		if ( $saved_image ) {
			wp_safe_redirect( get_site_url( get_current_blog_id(), $this->get_local_path() ) );
			exit;
		} else {
			$this->display_and_exit( "Please check permissions. Could not write image $dir" );
		}
	}

	public function display_and_exit( $message = false ) {
		global $wp_query;
		status_header( 200 );
		$wp_query->is_404 = false;

		// Send debug message in response headers.
		if ( $message ) {
			header( 'X-Uploads-By-Proxy: ' . $message );
		}

		foreach ( $this->response['headers'] as $name => $value ) {
			header( "$name: $value" );
		}

		echo $this->response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Only redirect for whitelisted paths
	 */
	public function allow_path() {
		$path = $this->get_remote_path();
		if ( empty( $path ) ) {
			return false;
		}

		$allowed_paths = array(
			$this->uploads_basedir(),
		);

		$allowed_paths = apply_filters( 'ubp_allowed_paths', $allowed_paths );

		foreach ( $allowed_paths as $value ) {
			if ( false !== strpos( $path, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return path to uploads folder, relative to WordPress root directory
	 * @var string
	 */
	public function uploads_basedir() {
		$uploads = wp_upload_dir();
		return wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
	}

	public function get_siteurl() {
		if ( isset( $this->siteurl ) ) {
			return $this->siteurl;
		}

		if ( defined( 'UBP_SITEURL' ) && is_array( UBP_SITEURL ) && isset( $_SERVER['HTTP_HOST'] ) && isset( UBP_SITEURL[ $_SERVER['HTTP_HOST'] ] ) ) {
			$url = wp_parse_url( 'https://' . UBP_SITEURL[ $_SERVER['HTTP_HOST'] ] . '/' );
			$url = $url['scheme'] . '://' . $url['host'] . $url['path'];
		} elseif ( defined( 'UBP_SITEURL' ) && false !== UBP_SITEURL ) {
			$url = wp_parse_url( UBP_SITEURL );
			$url = $url['scheme'] . '://' . $url['host'] . $url['path'];
		} elseif ( ! is_multisite() ) {
			// Nothing set... Get original siteurl from database
			remove_filter( 'option_siteurl', '_config_wp_siteurl' );
			$url = get_option( 'siteurl' );
			add_filter( 'option_siteurl', '_config_wp_siteurl' );
		}

		$this->siteurl = untrailingslashit( $url );

		return $this->siteurl;
	}

	public function get_domain() {
		if ( ! isset( $this->domain ) ) {
			$this->domain = wp_parse_url( $this->get_siteurl(), PHP_URL_HOST );
		}
		return $this->domain;
	}

	public function get_scheme() {
		if ( ! isset( $this->scheme ) ) {
			$this->scheme = wp_parse_url( $this->get_siteurl(), PHP_URL_SCHEME );
		}
		return $this->scheme;
	}

	public function get_auth() {
		if ( ! isset( $this->auth ) ) {
			$user = wp_parse_url( $this->get_siteurl(), PHP_URL_USER );
			$pass = wp_parse_url( $this->get_siteurl(), PHP_URL_PASS );

			if ( $user && $pass ) {
				$this->auth = $user . ':' . $pass . '@';
			} elseif ( $user ) {
				$this->auth = $user . '@';
			} else {
				$this->auth = '';
			}
		}
		return $this->auth;
	}

	public function get_local_path() {
		if ( isset( $this->local_path ) ) {
			return $this->local_path;
		}

		// If local install is in a subdirectory, modify path to request from WordPress root
		$local_wordpress_path = wp_parse_url( get_site_url(), PHP_URL_PATH ) . '/';
		$requested_path       = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( substr( $requested_path, 0, strlen( $local_wordpress_path ) ) === $local_wordpress_path ) {
			$requested_path = substr( $requested_path, strlen( $local_wordpress_path ) - 1, strlen( $requested_path ) );
		}

		$this->local_path = $requested_path;

		return $this->local_path;
	}

	public function get_remote_path() {
		if ( isset( $this->remote_path ) ) {
			return $this->remote_path;
		}

		// If remote install is in a subdirectory, prepend the remote path
		$remote_path = wp_parse_url( $this->get_siteurl(), PHP_URL_PATH );
		if ( ! empty( $remote_path ) ) {
			$this->remote_path = $remote_path . $this->get_local_path();
		} else {
			$this->remote_path = $this->get_local_path();
		}

		return $this->remote_path;
	}
}
