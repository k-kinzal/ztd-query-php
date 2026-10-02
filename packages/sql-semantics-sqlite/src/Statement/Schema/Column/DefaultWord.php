<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A DEFAULT column constraint whose value is written as an identifier.
 *
 * Rule: SQLITE-COLUMN-DEFAULT-WORD-001. SQLite reads an identifier after
 * DEFAULT as the text of its name, never as a column. The unquoted words TRUE
 * and FALSE are the exception: they are the integers 1 and 0. A quoted
 * `"true"` is the text again, so the word is kept with its quoting.
 * Source: https://sqlite.org/lang_createtable.html#the_default_clause
 * (grammar `ccons ::= DEFAULT scantok id`), https://sqlite.org/lang_expr.html#boolean_expressions.
 * Status: Implemented.
 *
 * @visibility public
 * @example Telling the text default from the truth default
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $text = $semantics->analyze('CREATE TABLE t (a DEFAULT unknown)')->statement->columns[0]->constraints[0];
 *     $truth = $semantics->analyze('CREATE TABLE t (a DEFAULT TRUE)')->statement->columns[0]->constraints[0];
 *     [$text->truth(), $text->word->name->value, $truth->truth()] // => [null, 'unknown', true]
 */
final class DefaultWord implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Word $word The identifier with its quoting
     */
    public function __construct(public readonly Word $word)
    {
        Check::input($word->quote !== WordQuote::Single, 'A single-quoted default is a string literal, not an identifier.');
    }

    /**
     * Answers the truth value of an unquoted TRUE or FALSE, or null when the word is a text default.
     */
    public function truth(): ?bool
    {
        if ($this->word->is('TRUE')) {
            return true;
        }

        return $this->word->is('FALSE') ? false : null;
    }

    /**
     * Derives nothing: the word is a constant.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT')->node($this->word);
    }
}
