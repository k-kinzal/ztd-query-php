<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
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
 *     $name = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName(new \SqlSemantics\Statement\Identifier\Name('batch'));
 *     $priority = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority(true, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('5'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange($name, \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind::User, $priority))->message() // => 'Thread priority -5 is outside the range of a USER resource group (ER_INVALID_THREAD_PRIORITY).'
 */
final class PriorityOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param ResourceGroupName $name The group operand, including its converted identity
     * @param ResourceGroupKind $kind The declared group kind
     * @param ThreadPriority $priority The rejected priority operand
     */
    public function __construct(public readonly ResourceGroupName $name, public readonly ResourceGroupKind $kind, public readonly ThreadPriority $priority)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Thread priority ' . $this->priority->describe() . ' is outside the range of a ' . $this->kind->value . ' resource group (ER_INVALID_THREAD_PRIORITY).';
    }
}
