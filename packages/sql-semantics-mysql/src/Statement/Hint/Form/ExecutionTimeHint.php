<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * MAX_EXECUTION_TIME(N): the time a top-level SELECT may run, in milliseconds; 0 leaves the max_execution_time limit in place.
 *
 * The number is kept as its decimal digits without leading zeros: the
 * server accepts up to 4294967295, and also the numbers from 2^63 to
 * 2^64 - 1, which do not fit a PHP integer (verified on a live 8.4
 * server). Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-execution-time.
 *
 * @visibility public
 * @example Writing the hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint('1000'))->text() // => 'MAX_EXECUTION_TIME(1000)'
 */
final class ExecutionTimeHint implements OptimizerHint
{
    use Snapshot;

    /**
     * @param string $milliseconds The limit, in decimal digits without leading zeros
     */
    public function __construct(public readonly string $milliseconds)
    {
        Check::input(preg_match('/\A(?:0|[1-9][0-9]*)\z/', $milliseconds) === 1, 'The limit of MAX_EXECUTION_TIME is a decimal number without leading zeros.');
    }

    /**
     * Answers the name of the hint.
     */
    public function name(): HintName
    {
        return HintName::MaxExecutionTime;
    }

    /**
     * Answers the hint as it is written in a hint comment.
     */
    public function text(): string
    {
        return 'MAX_EXECUTION_TIME(' . $this->milliseconds . ')';
    }
}
