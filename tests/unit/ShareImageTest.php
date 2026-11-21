<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Share_Image;

class ShareImageTest extends TestCase {

	private string $dir = '';

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['attachment_meta'] = [];
		$si_test_state['attached_files']  = [];
		$si_test_state['mime_types']      = [];
		$si_test_state['transients']      = [];
		$this->dir = sys_get_temp_dir() . '/jat-share-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $f ) {
			unlink( $f );
		}
		rmdir( $this->dir );
	}

	private function png_head( int $color_type, string $extra = '' ): string {
		return "\x89PNG\r\n\x1a\n" . "\x00\x00\x00\x0dIHDR" . pack( 'NN', 10, 10 ) . chr( 8 ) . chr( $color_type ) . "\x00\x00\x00" . $extra;
	}

	public function test_png_alpha_detection(): void {
		$this->assertTrue( Share_Image::png_has_alpha( $this->png_head( 6 ) ) );
		$this->assertTrue( Share_Image::png_has_alpha( $this->png_head( 4 ) ) );
		$this->assertFalse( Share_Image::png_has_alpha( $this->png_head( 2, 'IDATxxxx' ) ) );
		$this->assertTrue( Share_Image::png_has_alpha( $this->png_head( 3, "\x00\x00\x00\x01tRNS\x00" ) ) );
		$this->assertFalse( Share_Image::png_has_alpha( 'GIF89a' ) );
	}

	public function test_small_jpeg_is_shared_as_it_is(): void {
		$this->assertTrue( Share_Image::source_is_fine( 'image/jpeg', 1200, 630, 150000 ) );
		$this->assertFalse( Share_Image::source_is_fine( 'image/jpeg', 1536, 1024, 150000 ) );
		$this->assertFalse( Share_Image::source_is_fine( 'image/jpeg', 1200, 630, 900000 ) );
		$this->assertFalse( Share_Image::source_is_fine( 'image/png', 1200, 630, 150000 ) );
		$this->assertFalse( Share_Image::source_is_fine( 'image/webp', 1200, 630, 50000 ) );
	}

	public function test_mime_from_url(): void {
		$this->assertSame( 'image/webp', Share_Image::mime_from_url( 'https://example.com/a/b.WEBP?x=1' ) );
		$this->assertSame( 'image/jpeg', Share_Image::mime_from_url( 'https://example.com/b.jpeg' ) );
		$this->assertSame( '', Share_Image::mime_from_url( 'https://example.com/b' ) );
	}

	public function test_existing_copy_is_used_with_its_real_size(): void {
		global $si_test_state;
		file_put_contents( $this->dir . '/photo.png', 'x' );
		file_put_contents( $this->dir . '/photo-share.jpg', 'x' );
		$si_test_state['attached_files'][9]  = $this->dir . '/photo.png';
		$si_test_state['attachment_meta'][9] = [
			'width'  => 1536,
			'height' => 1024,
			'sizes'  => [
				Share_Image::SIZE => [ 'file' => 'photo-share.jpg', 'width' => 1200, 'height' => 800, 'mime-type' => 'image/jpeg' ],
			],
		];

		$image = Share_Image::for_attachment( 9, 'Working at the desk' );

		$this->assertSame( 'https://example.com/wp-content/uploads/photo-share.jpg', $image['url'] );
		$this->assertSame( 1200, $image['width'] );
		$this->assertSame( 800, $image['height'] );
		$this->assertSame( 'image/jpeg', $image['type'] );
		$this->assertSame( 'Working at the desk', $image['alt'] );
	}

	public function test_create_makes_a_jpeg_copy_even_when_the_theme_maps_jpeg_to_webp(): void {
		global $si_test_state;
		$big = $this->dir . '/photo.png';
		file_put_contents( $big, $this->png_head( 2, str_repeat( 'x', 400000 ) ) );
		$si_test_state['attached_files'][12]  = $big;
		$si_test_state['mime_types'][12]      = 'image/png';
		$si_test_state['attachment_meta'][12] = [ 'width' => 1536, 'height' => 1024, 'file' => 'photo.png' ];

		// A theme that writes every JPEG and PNG size as WebP.
		add_filter( 'image_editor_output_format', static function ( $formats ) {
			$formats['image/jpeg'] = 'image/webp';
			$formats['image/png']  = 'image/webp';
			return $formats;
		} );

		$calls = new \ArrayObject();
		$si_test_state['image_editor'] = static function ( $path ) use ( $calls ) {
			return new class( $calls ) {
				private $calls;
				private $w = 1536;
				private $h = 1024;
				public function __construct( $calls ) { $this->calls = $calls; }
				public function get_size() { return [ 'width' => $this->w, 'height' => $this->h ]; }
				public function resize( $w, $h, $crop ) { $this->calls[] = "resize $w $h"; $r = min( $w / $this->w, $h / $this->h ); $this->w = (int) round( $this->w * $r ); $this->h = (int) round( $this->h * $r ); }
				public function set_quality( $q ) { $this->calls[] = "quality $q"; }
				public function save( $dest, $mime ) {
					$formats = apply_filters( 'image_editor_output_format', [], $dest, $mime );
					$mime    = $formats[ $mime ] ?? $mime;
					$this->calls[] = "save $mime";
					file_put_contents( $dest, 'jpeg' );
					return [ 'path' => $dest, 'file' => basename( $dest ), 'width' => $this->w, 'height' => $this->h, 'mime-type' => $mime ];
				}
			};
		};

		$image = Share_Image::for_attachment( 12, 'Desk' );
		unset( $si_test_state['image_editor'], $si_test_state['filters']['image_editor_output_format'] );

		$this->assertSame( [ 'resize 1200 1200', 'quality 82', 'save image/jpeg' ], $calls->getArrayCopy() );
		$this->assertSame( 'https://example.com/wp-content/uploads/photo-share.jpg', $image['url'] );
		$this->assertSame( 1200, $image['width'] );
		$this->assertSame( 800, $image['height'] );
		$this->assertSame( 'image/jpeg', $image['type'] );
		$this->assertFileExists( $this->dir . '/photo-share.jpg' );
		$this->assertSame( 'photo-share.jpg', $si_test_state['attachment_meta'][12]['sizes'][ Share_Image::SIZE ]['file'] );
		$this->assertArrayNotHasKey( 'jat_si_share_12', $si_test_state['transients'] );
	}

	public function test_transparent_png_stays_png(): void {
		global $si_test_state;
		$file = $this->dir . '/logo.png';
		file_put_contents( $file, $this->png_head( 6, str_repeat( 'x', 400000 ) ) );
		$si_test_state['attached_files'][13]  = $file;
		$si_test_state['mime_types'][13]      = 'image/png';
		$si_test_state['attachment_meta'][13] = [ 'width' => 800, 'height' => 800 ];
		$saved = new \ArrayObject();
		$si_test_state['image_editor'] = static function ( $path ) use ( $saved ) {
			return new class( $saved ) {
				private $saved;
				public function __construct( $saved ) { $this->saved = $saved; }
				public function get_size() { return [ 'width' => 800, 'height' => 800 ]; }
				public function resize( $w, $h, $c ) { $this->saved[] = 'resize'; }
				public function set_quality( $q ) { $this->saved[] = 'quality'; }
				public function save( $dest, $mime ) { $this->saved[] = $mime; file_put_contents( $dest, 'png' ); return [ 'path' => $dest, 'file' => basename( $dest ), 'width' => 800, 'height' => 800, 'mime-type' => $mime ]; }
			};
		};

		$image = Share_Image::for_attachment( 13 );
		unset( $si_test_state['image_editor'] );

		$this->assertSame( [ 'image/png' ], $saved->getArrayCopy() );
		$this->assertStringEndsWith( '/logo-share.png', $image['url'] );
	}

	public function test_webp_served_for_a_jpeg_upload_gets_a_jpeg_copy(): void {
		global $si_test_state;
		// A JPEG upload whose full size a theme wrote as WebP: post MIME says JPEG, file is WebP.
		$file = $this->dir . '/wilds.webp';
		file_put_contents( $file, 'RIFF....WEBPVP8 ' );
		$si_test_state['attached_files'][14]  = $file;
		$si_test_state['mime_types'][14]      = 'image/jpeg';
		$si_test_state['attachment_meta'][14] = [ 'width' => 1200, 'height' => 630 ];
		$saved = new \ArrayObject();
		$si_test_state['image_editor'] = static function ( $path ) use ( $saved ) {
			return new class( $saved ) {
				private $saved;
				public function __construct( $saved ) { $this->saved = $saved; }
				public function get_size() { return [ 'width' => 1200, 'height' => 630 ]; }
				public function resize( $w, $h, $c ) { $this->saved[] = 'resize'; }
				public function set_quality( $q ) {}
				public function save( $dest, $mime ) { $this->saved[] = $mime; file_put_contents( $dest, 'jpg' ); return [ 'path' => $dest, 'file' => basename( $dest ), 'width' => 1200, 'height' => 630, 'mime-type' => $mime ]; }
			};
		};

		$image = Share_Image::for_attachment( 14 );
		unset( $si_test_state['image_editor'] );

		$this->assertSame( [ 'image/jpeg' ], $saved->getArrayCopy() );
		$this->assertStringEndsWith( '/wilds-share.jpg', $image['url'] );
	}

	public function test_unknown_url_keeps_url_without_dimensions(): void {
		$image = Share_Image::for_url( 'https://cdn.example.org/card.png', 'Card' );
		$this->assertSame( 'https://cdn.example.org/card.png', $image['url'] );
		$this->assertSame( 0, $image['width'] );
		$this->assertSame( 'image/png', $image['type'] );
	}
}
