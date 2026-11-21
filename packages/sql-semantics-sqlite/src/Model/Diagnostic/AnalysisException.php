<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Diagnostic;

use RuntimeException;

/**
 * SQL text that the selected grammar release does not accept.
 *
 *
 * @visibility public
 * @example Rejecting text outside the grammar
 *     (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELEC 1') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\AnalysisException
 */
final class AnalysisException extends RuntimeException implements \SqlSemantics\AnalysisException
{
}
