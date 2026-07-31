<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A manifest that cannot be trusted. The message is shown to the operator verbatim. */
final class ManifestError extends \RuntimeException {}
