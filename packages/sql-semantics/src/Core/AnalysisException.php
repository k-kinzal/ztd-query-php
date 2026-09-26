<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use RuntimeException;

/**
 * SQL that the selected language cannot accept, with the parser error as its cause.
 *
 * @visibility public
 * @example Reporting invalid SQL
 *     $error = new \SqlSemantics\Core\AnalysisException('Expected a statement');
 *     $error->getMessage() // => 'Expected a statement'
 */
final class AnalysisException extends RuntimeException
{
}
