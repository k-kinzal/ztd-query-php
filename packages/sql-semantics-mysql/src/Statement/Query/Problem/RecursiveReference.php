<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The nonrecursive part of a recursive common table expression, which fixes the columns a reference to the table sees.
 *
 * Rule: MYSQL-WITH-001. A recursive common table expression is a set
 * operation whose leading operands do not refer to it; its columns take
 * their types from those operands. A reference to it that is not in a later
 * operand of that set operation, which the server rejects, sees columns
 * that depend on this missing part. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility public
 * @example Describing the missing part
 *     (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference(new \SqlSemantics\Statement\Identifier\Name('c')))->describe() // => 'the nonrecursive part of recursive common table c'
 */
final class RecursiveReference implements MissingInput
{
    use Snapshot;

    /**
     * @param Name $table The common table expression
     */
    public function __construct(public readonly Name $table)
    {
    }

    /**
     * Describes the missing input.
     */
    public function describe(): string
    {
        return 'the nonrecursive part of recursive common table ' . $this->table->value;
    }
}
