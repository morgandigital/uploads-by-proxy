<?php

use Morgan\Uploads_By_Proxy\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase {

	public static function unsafe_request_uris(): array {
		return array(
			'encoded traversal'        => array( '/wp-content/uploads/%2e%2e/%2e%2e/wp-config.php' ),
			'plain traversal'          => array( '/wp-content/uploads/../../wp-config.php' ),
			'mixed case encoding'      => array( '/wp-content/uploads/%2E%2E/x.jpg' ),
			'encoded slash traversal'  => array( '/wp-content/uploads/..%2f..%2fwp-config.php' ),
			'dot segment'              => array( '/wp-content/uploads/./x.jpg' ),
			'null byte'                => array( '/wp-content/uploads/x.php%00.jpg' ),
			'backslash'                => array( '/wp-content/uploads/..%5c..%5cwp-config.php' ),
			'newline'                  => array( '/wp-content/uploads/x%0a.jpg' ),
			'relative'                 => array( 'wp-content/uploads/x.jpg' ),
			'empty'                    => array( '' ),
		);
	}

	#[DataProvider( 'unsafe_request_uris' )]
	public function test_rejects_unsafe_request_uris( string $uri ): void {
		$this->assertNull( Path::from_request_uri( $uri ) );
	}

	public function test_decodes_request_uri_and_drops_query(): void {
		$this->assertSame(
			'/wp-content/uploads/2024/01/my photo.jpg',
			Path::from_request_uri( '/wp-content/uploads/2024/01/my%20photo.jpg?ver=1#x' )
		);
	}

	public function test_double_encoding_is_decoded_only_once(): void {
		$this->assertSame( '/wp-content/uploads/%2e%2e/x.jpg', Path::from_request_uri( '/wp-content/uploads/%252e%252e/x.jpg' ) );
	}

	public function test_relative_to_requires_path_to_start_with_base(): void {
		$base = '/wp-content/uploads';

		$this->assertSame( '2024/01/x.jpg', Path::relative_to( '/wp-content/uploads/2024/01/x.jpg', $base ) );
		$this->assertSame( '2024/01/x.jpg', Path::relative_to( '/wp-content/uploads/2024/01/x.jpg', $base . '/' ) );

		// The old plugin matched the uploads path anywhere in the URL.
		$this->assertNull( Path::relative_to( '/foo/wp-content/uploads/x.jpg', $base ) );
		$this->assertNull( Path::relative_to( '/wp-content/uploads-evil/x.jpg', $base ) );
		$this->assertNull( Path::relative_to( '/wp-content/uploads', $base ) );
		$this->assertNull( Path::relative_to( '/wp-content/uploads/', $base ) );
		$this->assertNull( Path::relative_to( '/wp-content/uploads/2024/', $base ) );
		$this->assertNull( Path::relative_to( '/wp-content/uploads//etc/passwd', $base ) );
		$this->assertNull( Path::relative_to( '/wp-content/uploads/../x.jpg', $base ) );
	}

	public function test_subdirectory_multisite_paths(): void {
		// Subsite "site2" on a subdirectory multisite.
		$base = '/site2/wp-content/uploads/sites/2';

		$this->assertSame( '2024/01/x.jpg', Path::relative_to( '/site2/wp-content/uploads/sites/2/2024/01/x.jpg', $base ) );
		$this->assertNull( Path::relative_to( '/site2/wp-content/uploads/sites/3/2024/01/x.jpg', $base ) );

		// Network at the root: the subsite path is kept for the production URL.
		$this->assertSame( '/site2/wp-content/uploads/sites/2', Path::strip_prefix( $base, '/' ) );

		// Network in a subdirectory.
		$this->assertSame( '/site2/wp-content/uploads/sites/2', Path::strip_prefix( '/network/site2/wp-content/uploads/sites/2', '/network/' ) );
	}

	public function test_strip_prefix(): void {
		$this->assertSame( '/wp-content/uploads', Path::strip_prefix( '/wordpress/wp-content/uploads', '/wordpress' ) );
		$this->assertSame( '/wp-content/uploads', Path::strip_prefix( '/wp-content/uploads', '' ) );
		$this->assertSame( '/wordpressx/wp-content/uploads', Path::strip_prefix( '/wordpressx/wp-content/uploads', '/wordpress' ) );
		$this->assertSame( '', Path::strip_prefix( '/wordpress', '/wordpress/' ) );
	}

	public function test_encode(): void {
		$this->assertSame( '2024/01/my%20photo%23%3F.jpg', Path::encode( '2024/01/my photo#?.jpg' ) );
	}

	public function test_original_candidates(): void {
		$this->assertSame(
			array( '2024/01/photo.jpg', '2024/01/photo-scaled.jpg' ),
			Path::original_candidates( '2024/01/photo-300x200.jpg' )
		);
		$this->assertSame( array( '2024/01/photo.webp' ), Path::original_candidates( '2024/01/photo-scaled.webp' ) );
		$this->assertSame( array( '2024/01/photo.JPEG' ), Path::original_candidates( '2024/01/photo-rotated.JPEG' ) );
		$this->assertSame( array(), Path::original_candidates( '2024/01/photo.jpg' ) );
		$this->assertSame( array(), Path::original_candidates( '2024/01/document-300x200.pdf' ) );
	}
}
