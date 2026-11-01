<?php

declare(strict_types=1);

namespace SqlSemantics\Diagnostic;

use RuntimeException;

/**
 * SQL text that the selected grammar release does not accept.
 *
 * Invalid operand domains are reported separately by Construction\RejectedInput.
 * Missing or ambiguous names are facts of a successfully built operation.
 *
 * @visibility public
 * @example Rejecting text outside the grammar
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELEC 1') // throws \SqlSemantics\Diagnostic\AnalysisException
 */
final class AnalysisException extends RuntimeException
{
}
