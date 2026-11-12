<?php
/**
 * Admin notices for missing requirements.
 *
 * @package Uploads_By_Proxy
 */

namespace Morgan\Uploads_By_Proxy;

defined( 'ABSPATH' ) || exit;

/**
 * Warns when the plugin can't work.
 */
final class Admin_Notices {

	/**
	 * Hook the requirement checks.
	 */
	public static function requirements_check(): void {
		add_action( 'admin_notices', array( self::class, 'request_uploads_writable' ) );
		add_action( 'admin_footer', array( self::class, 'request_permalinks_enabled' ) );
	}

	/**
	 * Display an error message when permalinks are disabled
	 * Runs on admin_footer becuase admin_notices hook is too early to catch recent changes in permalinks
	 */
	public static function request_permalinks_enabled() {
		if ( '' !== get_option( 'permalink_structure' ) ) {
			return true;
		}
		?>
		<div id="ubp_permalinks_message" class="error">
			<p>
				<?php esc_html_e( 'Pretty Permalinks must be enabled for Uploads by Proxy to work.', 'uploads-by-proxy' ); ?>
				<?php
				echo wp_kses_post(
					sprintf(
						// translators: %1$s: Link to helphub "using permalinks", %2$s: Link to permalink options page, %3$s: Closing </a> tag
						__( '%1$sRead about using Permalinks%3$s, then %2$sgo to your Permalinks settings%3$s.', 'uploads-by-proxy' ),
						'<a href="https://wordpress.org/support/article/using-permalinks/" target="_blank">',
						'<a href="options-permalink.php">',
						'</a>'
					)
				);
				?>
			</p>
		</div>
		<?php

		return false;
	}

	/**
	 * Display an error message when uploads folder is not writable
	 */
	public static function request_uploads_writable() {
		$upload_dir = wp_upload_dir();
		if ( is_writable( $upload_dir['basedir'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			return true;
		}
		?>
		<div id="ubp_uploads_message" class="error">
			<p>
				<?php esc_html_e( 'The uploads directory must be enabled for Uploads by Proxy to work.', 'uploads-by-proxy' ); ?>
				<?php
				echo wp_kses_post(
					sprintf(
						// translators: %1$s: Link to helphub "changing file permissions", %2$s: Closing </a> tag
						__( '%1$sRead about changing file permissions%2$s, or try running:', 'uploads-by-proxy' ),
						'<a href="https://wordpress.org/support/article/changing-file-permissions/" target="_blank">',
						'</a>'
					)
				);
				?>
				<?php
				echo wp_kses_post(
					sprintf(
						// translators: %s: Path to uploads
						__( "<br/><code>chmod 755 '%s';</code>", 'uploads-by-proxy' ),
						$upload_dir['basedir']
					)
				);
				?>
			</p>
		</div>
		<?php
		return false;
	}
}
