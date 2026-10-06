<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An ALGORITHM or LOCK name the server of the release does not know.
 *
 * The server rejects the statement while parsing it, with
 * ER_UNKNOWN_ALTER_ALGORITHM or ER_UNKNOWN_ALTER_LOCK.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice(true, new \SqlSemantics\Statement\Identifier\Name('FAST')))->message() // => 'FAST is not a LOCK the server knows.'
 */
final class UnknownAlterChoice implements Diagnostic
{
    use Snapshot;

    /**
     * @param bool $lock Whether the name is a LOCK level (true) or an ALGORITHM (false)
     * @param Name $name The name as written
     */
    public function __construct(public readonly bool $lock, public readonly Name $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->name->value . ' is not ' . ($this->lock ? 'a LOCK' : 'an ALGORITHM') . ' the server knows.';
    }
}
