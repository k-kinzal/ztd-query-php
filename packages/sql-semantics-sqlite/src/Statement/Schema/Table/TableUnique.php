<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Rules\Definition\KeyTerms;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A UNIQUE table constraint.
 *
 * Rule: SQLITE-TABLE-UNIQUE-001. The grammar writes the key terms as ordering
 * terms, so any expression parses; SQLite accepts column names only, each
 * optionally with a collation and a sort order ("expressions prohibited in
 * PRIMARY KEY and UNIQUE constraints").
 * Source: https://sqlite.org/lang_createtable.html#unique_constraints.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the terms of a unique constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b, UNIQUE (a, b))');
 *     count($create->statement->constraints[0]->items[0]->terms) // => 2
 */
final class TableUnique implements TableConstraint
{
    use Snapshot;

    /**
     * @var non-empty-list<SortTerm> The key terms in written order
     */
    public readonly array $terms;

    /**
     * @param list<SortTerm> $terms The key terms in written order; at least one
     * @param ConflictResolution|null $conflict The written ON CONFLICT resolution
     */
    public function __construct(array $terms, public readonly ?ConflictResolution $conflict = null)
    {
        $this->terms = Check::listOf($terms, SortTerm::class, 'A key constraint has at least one term.', 1);
    }

    /**
     * Derives each term where the columns of the table are visible and reports a term that is no column name.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        foreach ($this->terms as $term) {
            $derivation->scalar($term->expression, $scope->row);
            if (!(new KeyTerms())->reference($term->expression)) {
                $derivation->report(new PrimaryKeyProblem(PrimaryKeyFlaw::ExpressionTerm));
            }
        }
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNIQUE')->symbol('(')->list($this->terms);
        $out->symbol(')');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
