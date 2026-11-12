<?php
/**
 * Fetch missing uploads from the production site.
 *
 * @package Uploads_By_Proxy
 */

namespace Morgan\Uploads_By_Proxy;

defined( 'ABSPATH' ) || exit;

/**
 * Handles 404 requests for files inside the uploads directory.
 */
final class Proxy {

	/**
	 * Result of wp_upload_dir() for the current site.
	 *
	 * @var array<string, mixed>
	 */
	private array $uploads;

	/**
	 * Hooked to 404_template. Only loads the proxy when WordPress serves a 404.
	 *
	 * @param string $template Path to the 404 template.
	 */
	public static function handle_404( $template ) {
		( new self() )->run();
		return $template;
	}

	/**
	 * Fetch the requested file from production, save it locally and redirect to it.
	 */
	public function run(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by Path::from_request_uri().
		$request     = Path::from_request_uri( $request_uri );
		if ( null === $request ) {
			return;
		}

		$this->uploads = wp_upload_dir( null, false );
		$base_path     = rawurldecode( (string) wp_parse_url( $this->uploads['baseurl'], PHP_URL_PATH ) );

		$relative = Path::relative_to( $request, $base_path );
		if ( null === $relative || ! $this->is_allowed_file( $relative ) ) {
			return;
		}

		$remote_root = $this->get_remote_root();
		if ( null === $remote_root ) {
			return;
		}

		// Loads wp_tempnam and the WP_Filesystem API.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Uploads URL on production, eg. https://www.example.com/site2/wp-content/uploads/sites/2
		$remote_base = $remote_root . Path::encode( Path::strip_prefix( $base_path, $this->get_local_root_path() ) );
		$local_file  = $this->uploads['basedir'] . '/' . $relative;

		// The web server didn't serve an existing file, so do it here instead of redirecting in a loop.
		if ( file_exists( $local_file ) ) {
			$this->serve( $local_file, $relative );
		}

		$tmp_file = $this->fetch( $remote_base . '/' . Path::encode( $relative ) );
		if ( null === $tmp_file ) {
			return;
		}

		if ( ! $this->save( $tmp_file, $local_file ) ) {
			header( 'X-Uploads-By-Proxy: Could not save file, check permissions of the uploads directory' );
			$this->serve( $tmp_file, $relative, true );
		}

		/**
		 * Whether to also download the original image when an intermediate size is requested,
		 * so new image sizes can be generated locally.
		 *
		 * @param bool   $fetch    Default true.
		 * @param string $relative Requested file, relative to the uploads directory.
		 */
		if ( apply_filters( 'ubp_fetch_originals', true, $relative ) ) {
			$this->fetch_originals( $remote_base, $relative );
		}

		wp_safe_redirect( $this->uploads['baseurl'] . '/' . Path::encode( $relative ) );
		exit;
	}

	/**
	 * Download original images of an intermediate image size, if missing locally.
	 *
	 * @param string $remote_base Uploads URL on production.
	 * @param string $relative    Requested file, relative to the uploads directory.
	 */
	private function fetch_originals( string $remote_base, string $relative ): void {
		foreach ( Path::original_candidates( $relative ) as $original ) {
			$local_file = $this->uploads['basedir'] . '/' . $original;

			if ( file_exists( $local_file ) || ! $this->is_allowed_file( $original ) ) {
				continue;
			}

			$tmp_file = $this->fetch( $remote_base . '/' . Path::encode( $original ) );
			if ( null !== $tmp_file && ! $this->save( $tmp_file, $local_file ) ) {
				wp_delete_file( $tmp_file );
			}
		}
	}

	/**
	 * Only proxy file types that can be uploaded to the site. Never PHP, .htaccess etc.
	 *
	 * @param string $relative File path relative to the uploads directory.
	 */
	private function is_allowed_file( string $relative ): bool {
		$filetype = wp_check_filetype( wp_basename( $relative ) );
		return false !== $filetype['ext'];
	}

	/**
	 * Download a file to a temporary location.
	 *
	 * @param string $url Remote file URL.
	 * @return string|null Path to the temporary file, or null on failure.
	 */
	private function fetch( string $url ): ?string {
		$tmp_file = wp_tempnam( $url );
		if ( ! $tmp_file ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 30,
				'stream'    => true,
				'filename'  => $tmp_file,
				'sslverify' => WP_DEBUG ? false : true,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || 0 === filesize( $tmp_file ) ) {
			wp_delete_file( $tmp_file );
			return null;
		}

		return $tmp_file;
	}

	/**
	 * Move a downloaded file into the uploads directory.
	 *
	 * @param string $tmp_file   Temporary file.
	 * @param string $local_file Destination inside the uploads directory.
	 */
	private function save( string $tmp_file, string $local_file ): bool {
		global $wp_filesystem;

		if ( ! WP_Filesystem() ) {
			return false;
		}

		$dir = dirname( $local_file );
		if ( ! wp_mkdir_p( $dir ) || ! $wp_filesystem->move( $tmp_file, $local_file, true ) ) {
			return false;
		}

		// Same permissions as wp_handle_upload() uses.
		$wp_filesystem->chmod( $local_file, fileperms( $dir ) & 0000666 );

		return true;
	}

	/**
	 * Send a file to the browser and exit.
	 *
	 * @param string $file     File to send.
	 * @param string $relative Requested file, used for the content type.
	 * @param bool   $delete   Delete the file after sending.
	 */
	private function serve( string $file, string $relative, bool $delete = false ): void {
		global $wp_query;

		$wp_query->is_404 = false;
		status_header( 200 );

		$filetype = wp_check_filetype( wp_basename( $relative ) );
		header( 'Content-Type: ' . $filetype['type'] );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile

		if ( $delete ) {
			wp_delete_file( $file );
		}

		exit;
	}

	/**
	 * Production URL matching the local root path, without a trailing slash.
	 *
	 * Configured with UBP_SITEURL as a URL, or as an array of local host => production host/URL
	 * pairs for multisites. Single sites fall back to the siteurl option in the database.
	 */
	private function get_remote_root(): ?string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		$url  = null;

		if ( defined( 'UBP_SITEURL' ) && is_array( UBP_SITEURL ) ) {
			$url = UBP_SITEURL[ $host ] ?? null;
			if ( is_string( $url ) && ! preg_match( '#^https?://#i', $url ) ) {
				$url = 'https://' . $url;
			}
		} elseif ( defined( 'UBP_SITEURL' ) && is_string( UBP_SITEURL ) && '' !== UBP_SITEURL ) {
			$url = UBP_SITEURL;
		} elseif ( ! is_multisite() ) {
			// Nothing set... Get original siteurl from database, ignoring WP_SITEURL.
			remove_filter( 'option_siteurl', '_config_wp_siteurl' );
			$url = get_option( 'siteurl' );
			add_filter( 'option_siteurl', '_config_wp_siteurl' );
		}

		if ( ! is_string( $url ) ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
			return null;
		}

		$auth = '';
		if ( isset( $parts['user'] ) ) {
			$auth = $parts['user'] . ( isset( $parts['pass'] ) ? ':' . $parts['pass'] : '' ) . '@';
		}

		$remote_host = strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

		// Requesting ourselves would recurse until the request times out.
		if ( $remote_host === $host ) {
			return null;
		}

		return $parts['scheme'] . '://' . $auth . $remote_host . untrailingslashit( $parts['path'] ?? '' );
	}

	/**
	 * Local path that maps to the production root.
	 *
	 * On multisites this is the network path, so subdirectory site paths like /site2/ are kept
	 * when requesting from production.
	 */
	private function get_local_root_path(): string {
		if ( is_multisite() ) {
			return rawurldecode( get_network()->path );
		}

		return rawurldecode( (string) wp_parse_url( get_site_url(), PHP_URL_PATH ) );
	}
}
