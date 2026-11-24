<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\Logging;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the logging assignments that PostgreSQL prepares before execution.
 * @visibility SqlSemantics
 */
final class SequenceLoggingPrefix
{
    /**
     * Requires one possible initial mode for the actions before the final assignment.
     *
     * @param list<SequenceLogging> $modes
     */
    public static function check(array $modes): void
    {
        foreach ($modes as $offset => $mode) {
            Check::input(
                $offset + 1 === count($modes) || $mode === $modes[0],
                'A sequence logging prefix must request one consistent mode.'
            );
        }
    }
}
