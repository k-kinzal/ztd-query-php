<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Diagnostic;

use RuntimeException;

/**
 * SQL text that the selected grammar release does not accept.
 *
 * @visibility public
 * @example Rejecting invalid SQL
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $semantics->analyze('SELECT (') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\AnalysisException
 */
final class AnalysisException extends RuntimeException implements \SqlSemantics\AnalysisException
{
}
