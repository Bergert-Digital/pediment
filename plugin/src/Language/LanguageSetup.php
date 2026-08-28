<?php
/**
 * Reconciles a multilingual plugin's own settings against the manifest's
 * `languages`. The seam `wp pediment languages` resolves, parallel to the
 * LanguageProvider seam the seed run uses.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface LanguageSetup {
	/**
	 * @param array<string,\Pediment\Seeder\LanguageSpec> $languages Declaration order, default first.
	 * @return array{changes:string[],errors:string[]}
	 */
	public function configure( array $languages, string $default, bool $dryRun = false ): array;
}
