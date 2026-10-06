<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The table constraints written one after another without a comma between them.
 *
 * Rule: SQLITE-CONSTRAINT-RUN-001. The comma between table constraints is
 * optional, but it is not without meaning: a `CONSTRAINT name` clause stays in
 * effect for the constraints that follow it until the next comma. A table
 * definition therefore holds its table constraints as comma-separated runs,
 * and a constraint name belongs to its run.
 * Source: https://sqlite.org/syntax/create-table-stmt.html (grammar `tconscomma`).
 * Status: Implemented.
 *
 * @visibility public
 * @example Telling a named constraint from a name the comma cuts off
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $named = $semantics->analyze('CREATE TABLE t (a, CONSTRAINT c CHECK (a > 0))');
 *     $cut = $semantics->analyze('CREATE TABLE t (a, CONSTRAINT c, CHECK (a > 0))');
 *     [count($named->statement->constraints), count($cut->statement->constraints)] // => [1, 2]
 */
final class ConstraintRun implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<TableConstraint> The constraints of the run in written order
     */
    public readonly array $items;

    /**
     * @param list<TableConstraint> $items The constraints of the run in written order; at least one
     */
    public function __construct(array $items)
    {
        $this->items = Check::listOf($items, TableConstraint::class, 'A constraint run holds at least one table constraint.', 1);
    }

    /**
     * Writes the constraints without a comma between them.
     */
    public function render(Output $out): void
    {
        foreach ($this->items as $item) {
            $out->node($item);
        }
    }
}
