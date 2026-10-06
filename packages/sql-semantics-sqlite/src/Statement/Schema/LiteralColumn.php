<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A column named by a string literal in a key constraint or an index term.
 *
 * Rule: SQLITE-LITERAL-COLUMN-001. For compatibility with old databases,
 * SQLite reads a string literal written as a term of a PRIMARY KEY or UNIQUE
 * constraint (under any parentheses and COLLATE clauses) or of CREATE INDEX
 * (under parentheses and at most one COLLATE clause) as an identifier, which
 * must name a column of the table. The literal keeps its spelling; the name
 * is its value. Facts: those of the column the name resolves to in the table
 * being defined or indexed (SQLITE-COLUMN-LOOKUP-001); a name that is no
 * column is a missing column, as "no such column" is for SQLite. A string at
 * any other position, a doubly collated string in an index term included, is
 * the text constant it is.
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key,
 * https://sqlite.org/lang_createindex.html (and `sqlite3StringToId()` in
 * build.c of the release). Status: Implemented.
 *
 * @visibility public
 * @example Resolving a string key term to the column it names
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("CREATE TABLE t (a INTEGER, PRIMARY KEY ('a'))");
 *     $term = $create->statement->constraints[0]->items[0]->terms[0]->expression;
 *     [$term->literal->value, $create->facts->scalar($term)->resolution->slot->column === $create->declarations()[0]->columns[0], $create->declarations()[0]->implicit[0]->column === $create->declarations()[0]->columns[0], $create->toString()] // => ['a', true, true, "CREATE TABLE t (a INTEGER, PRIMARY KEY ('a'))"]
 */
final class LiteralColumn implements Scalar
{
    use Snapshot;

    /**
     * @param TextLiteral $literal The string literal whose value is the column name
     */
    public function __construct(public readonly TextLiteral $literal)
    {
    }

    /**
     * Answers the column name the literal spells.
     */
    public function name(): Name
    {
        return new Name($this->literal->value);
    }

    /**
     * Derives the facts of the column the name resolves to; the literal itself keeps the facts of the text it spells.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->literal, $environment);

        return (new ColumnFacts())->of((new ColumnResolver())->find($environment, $this->name()));
    }

    /**
     * Writes the literal as written.
     */
    public function render(Output $out): void
    {
        $out->node($this->literal);
    }
}
