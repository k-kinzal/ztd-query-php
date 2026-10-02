<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownStorage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A generated column constraint: the expression that computes the column.
 *
 * Rule: SQLITE-COLUMN-GENERATED-001. The expression may refer to the other
 * columns of the table and "may not directly reference the ROWID"; it is
 * derived at a position that sees the columns of the table being defined
 * only. The keywords GENERATED ALWAYS are optional and are not written. The
 * grammar accepts any identifier after the expression; SQLite compares its
 * written text with `virtual` and `stored` and rejects every other word, so
 * the word is kept with its quoting and an unknown one is a diagnostic. A
 * column without the word is VIRTUAL.
 * Source: https://sqlite.org/gencol.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a generated column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b GENERATED ALWAYS AS (a + 1) VIRTUAL)');
 *     [$create->statement->columns[1]->constraints[0]->storage(), $create->toString()] // => [\SqlSemantics\Platform\Sqlite\Statement\Schema\Column\GeneratedStorage::Virtual, 'CREATE TABLE t (a, b GENERATED ALWAYS AS (a + 1) VIRTUAL)']
 */
final class Generated implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Scalar $expression The expression that computes the column
     * @param Word|null $word The word written after the expression
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Word $word = null)
    {
        Check::input($word?->quote !== WordQuote::Single, 'The word after a generated column expression is an identifier, not a string.');
    }

    /**
     * Answers how the column is stored, or null when the written word is neither VIRTUAL nor STORED.
     */
    public function storage(): ?GeneratedStorage
    {
        if ($this->word === null || $this->word->is('VIRTUAL')) {
            return GeneratedStorage::Virtual;
        }

        return $this->word->is('STORED') ? GeneratedStorage::Stored : null;
    }

    /**
     * Derives the expression where the columns of the table are visible and reports an unknown storage word.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        $derivation->scalar($this->expression, $scope->columns);
        if ($this->word !== null && $this->storage() === null) {
            $derivation->report(new UnknownStorage($this->word));
        }
    }

    /**
     * Writes the clause without the optional GENERATED ALWAYS keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->symbol('(')->node($this->expression)->symbol(')')->node($this->word);
    }
}
