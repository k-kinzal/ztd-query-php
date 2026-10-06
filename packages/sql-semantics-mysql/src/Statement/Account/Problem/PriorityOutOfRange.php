<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A THREAD_PRIORITY of CREATE RESOURCE GROUP outside the range of the group's type.
 *
 * System groups take -20 to 0, user groups 0 to 19; the server rejects
 * another value with ER_INVALID_THREAD_PRIORITY.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange('USER', '-5'))->message() // => 'Thread priority -5 is outside the range of a USER resource group (ER_INVALID_THREAD_PRIORITY).'
 */
final class PriorityOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $type The type of the group
     * @param string $priority The priority as written
     */
    public function __construct(public readonly string $type, public readonly string $priority)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Thread priority ' . $this->priority . ' is outside the range of a ' . $this->type . ' resource group (ER_INVALID_THREAD_PRIORITY).';
    }
}
