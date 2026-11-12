<?php
/**
 * Request path validation and mapping.
 *
 * Pure string handling with no WordPress dependencies, so it can be unit tested.
 *
 * @package Uploads_By_Proxy
 */

namespace Morgan\Uploads_By_Proxy;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and maps request paths to files inside the uploads directory.
 */
final class Path {

	/**
	 * Image extensions WordPress generates intermediate sizes for.
	 */
	private const IMAGE_EXTENSIONS = 'jpe?g|jpe|png|gif|webp|avif|heic';

	/**
	 * Extract and decode the path from a request URI.
	 *
	 * @param string $uri Raw request URI, eg. $_SERVER['REQUEST_URI'].
	 * @return string|null Decoded absolute path, or null if the path is unsafe.
	 */
	public static function from_request_uri( string $uri ): ?string {
		$path = explode( '?', $uri, 2 )[0];
		$path = explode( '#', $path, 2 )[0];

		if ( '' === $path || '/' !== $path[0] ) {
			return null;
		}

		$path = rawurldecode( $path );

		return self::is_safe( $path ) ? $path : null;
	}

	/**
	 * Check a decoded path for traversal and other filesystem tricks.
	 *
	 * @param string $path Decoded path.
	 */
	public static function is_safe( string $path ): bool {
		// Null bytes, control characters and backslashes (Windows separators).
		if ( preg_match( '/[\x00-\x1f\x7f\\\\]/', $path ) ) {
			return false;
		}

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get a file path relative to a base directory path.
	 *
	 * @param string $path Decoded request path.
	 * @param string $base Decoded base path, eg. "/site2/wp-content/uploads/sites/2".
	 * @return string|null Relative path without a leading slash, or null if outside the base.
	 */
	public static function relative_to( string $path, string $base ): ?string {
		$prefix = rtrim( $base, '/' ) . '/';

		if ( ! str_starts_with( $path, $prefix ) ) {
			return null;
		}

		$relative = substr( $path, strlen( $prefix ) );

		if ( '' === $relative || str_starts_with( $relative, '/' ) || str_ends_with( $relative, '/' ) || str_contains( $relative, '//' ) || ! self::is_safe( $relative ) ) {
			return null;
		}

		return $relative;
	}

	/**
	 * Remove a leading directory prefix from a path, if present.
	 *
	 * @param string $path   Path, eg. "/wordpress/wp-content/uploads".
	 * @param string $prefix Prefix, eg. "/wordpress".
	 */
	public static function strip_prefix( string $path, string $prefix ): string {
		$prefix = rtrim( $prefix, '/' );

		if ( '' === $prefix ) {
			return $path;
		}

		if ( $path === $prefix ) {
			return '';
		}

		if ( str_starts_with( $path, $prefix . '/' ) ) {
			return substr( $path, strlen( $prefix ) );
		}

		return $path;
	}

	/**
	 * URL encode each segment of a decoded path.
	 *
	 * @param string $path Decoded path.
	 */
	public static function encode( string $path ): string {
		return implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
	}

	/**
	 * Guess the original upload(s) an intermediate image size was generated from.
	 *
	 * "photo-300x200.jpg" may come from "photo.jpg" or, since WordPress 5.3,
	 * a big image scaled to "photo-scaled.jpg" whose original is "photo.jpg".
	 *
	 * @param string $relative Relative path of the requested file.
	 * @return string[] Relative paths of candidate originals.
	 */
	public static function original_candidates( string $relative ): array {
		$extensions = self::IMAGE_EXTENSIONS;

		if ( preg_match( "/^(.+)-\d+x\d+\.($extensions)$/i", $relative, $matches ) ) {
			return array(
				"{$matches[1]}.{$matches[2]}",
				"{$matches[1]}-scaled.{$matches[2]}",
			);
		}

		if ( preg_match( "/^(.+)-(?:scaled|rotated)\.($extensions)$/i", $relative, $matches ) ) {
			return array( "{$matches[1]}.{$matches[2]}" );
		}

		return array();
	}
}
