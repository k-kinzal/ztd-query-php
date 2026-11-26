<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Rules\Definition\KeyTerms;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;

/**
 * A PRIMARY KEY table constraint.
 *
 * Rule: SQLITE-TABLE-PRIMARY-KEY-001. The grammar writes the key terms as
 * ordering terms, so any expression parses; SQLite accepts column names only,
 * each optionally with a collation and a sort order ("expressions prohibited
 * in PRIMARY KEY and UNIQUE constraints"). A column name is an identifier, a
 * double-quoted word or a string literal (LiteralColumn; a plain string
 * literal term is refused at construction, SQLITE-KEY-TERM-001). Each term is
 * derived where the columns and the row identifier of the table are visible.
 * Unlike the column constraint, a single-column key of declared type INTEGER
 * is the row identifier whatever sort order is written. AUTOINCREMENT is
 * written inside the parentheses after the last term.
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key,
 * https://sqlite.org/lang_createtable.html#rowid. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TablePrimaryKey implements TableConstraint
{
    use Snapshot;

    /**
     * @var non-empty-list<SortTerm> The key terms in written order
     */
    public readonly array $terms;

    /**
     * @param list<SortTerm> $terms The key terms in written order; at least one
     * @param ConflictResolution|null $conflict The written ON CONFLICT resolution
     * @param bool $autoincrement Whether AUTOINCREMENT is written after the last key term
     */
    public function __construct(array $terms, public readonly ?ConflictResolution $conflict = null, public readonly bool $autoincrement = false)
    {
        $this->terms = Check::listOf($terms, SortTerm::class, 'A key constraint has at least one term.', 1);
        foreach ($this->terms as $term) {
            Check::input(!(new KeyTerms())->string($term->expression, null), 'A string literal written as a key term names a column: write it as a LiteralColumn.');
        }
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
        $out->keyword('PRIMARY', 'KEY')->symbol('(')->list($this->terms);
        if ($this->autoincrement) {
            $out->keyword('AUTOINCREMENT');
        }
        $out->symbol(')');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
