<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;

/**
 * One ON CONFLICT clause of an INSERT: what to do when a row violates a uniqueness constraint.
 *
 * A clause without assignments is DO NOTHING; a clause with assignments is
 * DO UPDATE SET, optionally restricted by a predicate.
 * Source: https://sqlite.org/lang_upsert.html.
 *
 * @visibility SqlSemantics
 * @example Refusing a predicate on DO NOTHING
 *     new \SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert(null, [], new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 */
final class Upsert implements Node
{
    use Snapshot;

    /**
     * @var list<Assignment|RowAssignment> The assignments of DO UPDATE; none for DO NOTHING
     */
    public readonly array $assignments;

    /**
     * @param ConflictTarget|null $target The constraint the clause reacts to; null for any constraint
     * @param list<Assignment|RowAssignment> $assignments The assignments of DO UPDATE; none for DO NOTHING
     * @param Scalar|null $where The predicate of DO UPDATE
     * @throws InvalidConstruction When an assignment is of no assignment class
     */
    public function __construct(public readonly ?ConflictTarget $target = null, array $assignments = [], public readonly ?Scalar $where = null)
    {
        $this->assignments = (new ClosedList())->of($assignments, [Assignment::class, RowAssignment::class], 'An upsert assigns columns or column rows.');
        Check::input($this->assignments !== [] || $where === null, 'DO NOTHING takes no predicate.');
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', 'CONFLICT')->node($this->target)->keyword('DO');
        if ($this->assignments === []) {
            $out->keyword('NOTHING');

            return;
        }
        $out->keyword('UPDATE', 'SET')->list($this->assignments);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
