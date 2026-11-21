<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Diagnostic;

use RuntimeException;

/**
 * SQL text that the selected grammar release does not accept.
 *
 *
 * @visibility public
 * @example Rejecting text outside the grammar
 *     (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELEC 1') // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\AnalysisException
 */
final class AnalysisException extends RuntimeException implements \SqlSemantics\AnalysisException
{
}
