<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Reading_Time;

class ReadingTimeTest extends ParityTestCase {

	public function test_empty_content_is_zero(): void {
		$this->assertSame( 0, Reading_Time::minutes_for( '' ) );
		$this->assertSame( 0, Reading_Time::minutes_for( '<p> </p>[gallery]' ) );
	}

	public function test_short_content_rounds_up_to_one_minute(): void {
		$this->assertSame( 1, Reading_Time::minutes_for( '<p>Just a few words.</p>' ) );
	}

	public function test_words_per_minute(): void {
		$this->assertSame( 1, Reading_Time::minutes_for( str_repeat( 'word ', 238 ) ) );
		$this->assertSame( 2, Reading_Time::minutes_for( str_repeat( 'word ', 239 ) ) );
		$this->assertSame( 5, Reading_Time::minutes_for( '<p>' . str_repeat( 'word ', 1190 ) . '</p>' ) );
	}

	public function test_cjk_counts_characters(): void {
		$this->assertSame( 2, Reading_Time::minutes_for( str_repeat( '日本語', 200 ) ) ); // 600 characters.
		$this->assertSame( 1, Reading_Time::minutes_for( str_repeat( '한', 400 ) ) );
	}

	public function test_label_pluralises(): void {
		$this->assertSame( '1 minute', Reading_Time::label( 1 ) );
		$this->assertSame( '4 minutes', Reading_Time::label( 4 ) );
	}

	public function test_minutes_requires_a_post(): void {
		$this->assertSame( 0, Reading_Time::minutes( 999 ) );
	}

	public function test_shortcode_is_empty_without_a_post(): void {
		$this->assertSame( '', Reading_Time::shortcode( [] ) );
	}
}
