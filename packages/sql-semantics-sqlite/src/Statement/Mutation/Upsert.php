<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One ON CONFLICT clause of an INSERT: what to do when a row violates a uniqueness constraint.
 *
 * A clause without assignments is DO NOTHING; a clause with assignments is
 * DO UPDATE SET, optionally restricted by a predicate.
 * Source: https://sqlite.org/lang_upsert.html.
 *
 * @visibility public
 * @example Reading an upsert clause
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO t VALUES (1) ON CONFLICT (a) DO UPDATE SET a = excluded.a WHERE a < 5');
 *     [count($insert->statement->upserts[0]->assignments), $insert->statement->upserts[0]->where !== null] // => [1, true]
 * @example Refusing a predicate on DO NOTHING
 *     new \SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert(null, [], new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
