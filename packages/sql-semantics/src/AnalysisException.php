<?php

declare(strict_types=1);

namespace SqlSemantics;

use Throwable;

/**
 * The supplied SQL or declaration context could not be analyzed.
 *
 * @visibility public
 * @example Giving analysis failures a common exception type
 *     $error = new class('Invalid SQL') extends \RuntimeException implements \SqlSemantics\AnalysisException {};
 *     $error->getMessage() // => 'Invalid SQL'
 */
interface AnalysisException extends Throwable
{
}
