<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FunctionShapes;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A function call, or ROWS FROM of several, used as a FROM item.
 *
 * Mirrors PostgreSQL's `RangeFunction`. The facts follow
 * PG-FUNCTION-TABLE-001. A function call may refer to the FROM items before
 * it whether or not LATERAL is written; the word is kept as written.
 * Column definitions are written after the alias for one function outside
 * ROWS FROM, and per function inside it.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLEFUNCTIONS. Status: Implemented.
 *
 * @visibility public
 * @example Reading a function in FROM
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT * FROM f() WITH ORDINALITY AS x (a integer)');
 *     [$query->statement->from->ordinality, $query->statement->from->definitions[0]->name->value, $query->toString()] // => [true, 'a', 'SELECT * FROM f() WITH ORDINALITY AS x (a INTEGER)']
 * @example Refusing several functions outside ROWS FROM
 *     $call = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableFunction(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     $other = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableFunction(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable([$call, $other]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FunctionTable implements Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<TableFunction> The function calls in written order
     */
    public readonly array $functions;

    /**
     * @var list<Name> The column names written after the correlation name
     */
    public readonly array $columns;

    /**
     * @var list<TypedColumn> The column definitions written after the correlation name or AS
     */
    public readonly array $definitions;

    /**
     * @param list<TableFunction> $functions The function calls in written order; one unless ROWS FROM is written
     * @param bool $rowsFrom Whether ROWS FROM is written
     * @param bool $ordinality Whether WITH ORDINALITY is written
     * @param Name|null $alias The correlation name
     * @param list<Name> $columns The column names written after the correlation name
     * @param list<TypedColumn> $definitions The column definitions written after the correlation name or AS
     * @param bool $lateral Whether LATERAL is written
     */
    public function __construct(
        array $functions,
        public readonly bool $rowsFrom = false,
        public readonly bool $ordinality = false,
        public readonly ?Name $alias = null,
        array $columns = [],
        array $definitions = [],
        public readonly bool $lateral = false,
    ) {
        $this->functions = Check::listOf($functions, TableFunction::class, 'A function FROM item calls at least one function.', 1);
        $this->columns = Check::listOf($columns, Name::class, 'Column aliases are names.');
        $this->definitions = Check::listOf($definitions, TypedColumn::class, 'Column definitions are typed columns.');
        Check::input($rowsFrom || (count($this->functions) === 1 && $this->functions[0]->definitions === []), 'Several functions, and column definitions per function, are written inside ROWS FROM.');
        Check::input($alias !== null || $this->columns === [], 'Column aliases are written after a correlation name.');
        Check::input($this->columns === [] || $this->definitions === [], 'A function FROM item has column names or column definitions, not both.');
    }

    /**
     * Derives the calls and the columns of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FunctionShapes())->derive($this, $derivation, $environment);
    }

    /**
     * Answers the name the occurrence is known by without an alias: the name of its first function.
     */
    public function name(): ?Name
    {
        return (new FunctionShapes())->functionName($this->functions[0]);
    }

    /**
     * Writes LATERAL, the calls, WITH ORDINALITY and the alias with its columns or column definitions.
     */
    public function render(Output $out): void
    {
        if ($this->lateral) {
            $out->keyword('LATERAL');
        }
        if ($this->rowsFrom) {
            $out->keyword('ROWS', 'FROM')->symbol('(')->list($this->functions)->symbol(')');
        } else {
            $out->node($this->functions[0]);
        }
        if ($this->ordinality) {
            $out->keyword('WITH', 'ORDINALITY');
        }
        (new AliasSpelling())->write($out, $this->alias, $this->columns);
        if ($this->definitions !== []) {
            if ($this->alias === null) {
                $out->keyword('AS');
            }
            $out->symbol('(')->list($this->definitions)->symbol(')');
        }
    }
}
