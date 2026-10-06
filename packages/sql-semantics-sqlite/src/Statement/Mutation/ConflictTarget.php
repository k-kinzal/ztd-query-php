<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The uniqueness constraint an upsert clause reacts to: indexed columns and the predicate of a partial index.
 *
 * Source: https://sqlite.org/lang_upsert.html.
 *
 * @visibility public
 * @example Reading a conflict target
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO t VALUES (1) ON CONFLICT (a) WHERE a > 0 DO NOTHING');
 *     [count($insert->statement->upserts[0]->target->terms), $insert->statement->upserts[0]->target->where !== null] // => [1, true]
 */
final class ConflictTarget implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<SortTerm> The indexed columns or expressions in written order
     */
    public readonly array $terms;

    /**
     * @param list<SortTerm> $terms The indexed columns or expressions; at least one
     * @param Scalar|null $where The predicate of a partial index
     */
    public function __construct(array $terms, public readonly ?Scalar $where = null)
    {
        $this->terms = Check::listOf($terms, SortTerm::class, 'A conflict target names at least one indexed column.', 1);
    }

    /**
     * Writes the target.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->terms)->symbol(')');
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
