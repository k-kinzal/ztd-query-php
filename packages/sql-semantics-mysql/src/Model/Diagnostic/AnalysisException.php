<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Diagnostic;

use RuntimeException;

/**
 * SQL text that the selected grammar release does not accept.
 *
 *
 * @visibility public
 * @example Rejecting text outside the grammar
 *     (new \SqlSemantics\Platform\MySql\Semantics())->analyze('SELEC 1') // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException
 */
final class AnalysisException extends RuntimeException implements \SqlSemantics\AnalysisException
{
}
