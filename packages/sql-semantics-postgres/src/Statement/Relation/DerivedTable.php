<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\ColumnAliases;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A subquery in FROM, optionally LATERAL and renamed.
 *
 * Mirrors PostgreSQL's `RangeSubselect`. Rule: PG-DERIVED-TABLE-001. The
 * query is derived in the environment the FROM clause gives it: the
 * enclosing query, and, when LATERAL is written, the FROM items before it.
 * The occurrence has one column per output column of the query, renamed by
 * the column aliases; the query writes the parentheses it is enclosed in.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM,
 * https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-SUBQUERIES. Status: Implemented.
 *
 * @visibility public
 * @example Reading a derived table
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT x.b FROM (SELECT 1 AS a) AS x (b)');
 *     [$query->statement->from->alias->value, $query->field(0)->type->descriptor->name()] // => ['x', 'integer']
 */
final class DerivedTable implements Relation
{
    use Snapshot;

    /**
     * @var list<Name> The column names written after the correlation name
     */
    public readonly array $columns;

    /**
     * @param Query $query The query inside the parentheses
     * @param Name|null $alias The correlation name
     * @param list<Name> $columns The column names written after the correlation name
     * @param bool $lateral Whether the query may refer to the FROM items before it
     */
    public function __construct(public readonly Query $query, public readonly ?Name $alias = null, array $columns = [], public readonly bool $lateral = false)
    {
        $this->columns = Check::listOf($columns, Name::class, 'Column aliases are names.');
        Check::input($alias !== null || $this->columns === [], 'Column aliases are written after a correlation name.');
    }

    /**
     * Derives the query and the columns of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $fact = $derivation->query($this->query, $environment);

        return new RelationFact((new ColumnAliases())->apply((new ColumnAliases())->fromQuery($fact), $this->alias, $this->columns, $derivation));
    }

    /**
     * Writes LATERAL, the query in parentheses and the alias.
     */
    public function render(Output $out): void
    {
        if ($this->lateral) {
            $out->keyword('LATERAL');
        }
        $out->symbol('(')->node($this->query)->symbol(')');
        (new AliasSpelling())->write($out, $this->alias, $this->columns);
    }
}
