<?php
/**
 * Reject hex/rgb/hsl color literals inside src/blocks/.
 *
 * Use theme.json CSS custom properties instead: var(--wp--preset--color--*).
 */

namespace Starter\Sniffs;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class NoColorLiteralSniff implements Sniff {

	/** @var string[] */
	public $applyToPath = array( 'src/blocks/' );

	public function register(): array {
		return array( T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML );
	}

	/**
	 * @param File $phpcsFile
	 * @param int  $stackPtr
	 */
	public function process( File $phpcsFile, $stackPtr ): void {
		$filename     = $phpcsFile->getFilename();
		$matched_path = false;
		foreach ( $this->applyToPath as $needle ) {
			if ( false !== strpos( str_replace( '\\', '/', $filename ), $needle ) ) {
				$matched_path = true;
				break;
			}
		}
		if ( ! $matched_path ) {
			return;
		}

		$tokens  = $phpcsFile->getTokens();
		$content = (string) $tokens[ $stackPtr ]['content'];

		if ( preg_match( '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?\b/', $content )
			|| preg_match( '/\brgb[a]?\s*\(/i', $content )
			|| preg_match( '/\bhsl[a]?\s*\(/i', $content )
		) {
			$phpcsFile->addError(
				'Color literals are forbidden in src/blocks/. Use theme.json tokens via var(--wp--preset--color--*).',
				$stackPtr,
				'ColorLiteralFound'
			);
		}
	}
}
