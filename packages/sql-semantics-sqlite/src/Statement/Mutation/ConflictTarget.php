<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;

/**
 * The uniqueness constraint an upsert clause reacts to: indexed columns and the predicate of a partial index.
 *
 * Source: https://sqlite.org/lang_upsert.html.
 *
 * @visibility SqlSemantics
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
