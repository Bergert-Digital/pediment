<?php
/**
 * Mapper: pediment/pull-quote → core/quote with the pull-quote style.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert\Mappers;

use Pediment\Convert\Attrs;
use Pediment\Convert\Build;
use Pediment\Convert\Context;
use Pediment\Convert\Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A quote.is-style-pull-quote → one paragraph; the citation becomes <cite>.
 */
final class PullQuoteMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/pull-quote' );
	}

	public function map( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$quote = Attrs::rich( $attrs, 'quote' );
		if ( '' === $quote ) {
			return null;
		}
		$out              = Attrs::put( array(), 'align', $context->align( $block ) );
		$out['className'] = $context->className( $block, 'core/quote', 'is-style-pull-quote' );
		return Build::quote( $out + $context->carry( $block, null ), array( Build::paragraph( $quote ) ), Attrs::rich( $attrs, 'citation' ) );
	}
}
