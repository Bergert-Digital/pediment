<?php

use Pediment\Convert\Diff;

class DiffTest extends WP_UnitTestCase {

	public function test_equal_texts_have_no_diff(): void {
		$this->assertSame( '', Diff::unified( "a\nb", "a\nb" ) );
	}

	public function test_a_replaced_block_shows_removed_then_added_lines_with_context(): void {
		$before = "a\nb\n<!-- wp:pediment/x /-->\nc\nd\ne\nf\ng\n";
		$after  = "a\nb\n<!-- wp:group -->\n<div></div>\n<!-- /wp:group -->\nc\nd\ne\nf\ng\n";
		$this->assertSame(
			"@@ -1 +1 @@\n a\n b\n-<!-- wp:pediment/x /-->\n+<!-- wp:group -->\n+<div></div>\n+<!-- /wp:group -->\n c\n d\n",
			Diff::unified( $before, $after )
		);
	}

	public function test_long_diffs_are_cut(): void {
		$diff = Diff::unified( 'x', str_repeat( "line\n", 10000 ) );
		$this->assertLessThan( Diff::MAX_BYTES + 100, strlen( $diff ) );
		$this->assertStringEndsWith( "… diff truncated at 20000 bytes\n", $diff );
	}
}
