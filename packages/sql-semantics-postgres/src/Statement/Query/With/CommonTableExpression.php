<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * One common table expression: a name, optional column names, and the statement that computes its rows.
 *
 * Mirrors PostgreSQL's `CommonTableExpr`. The statement is a query or a
 * data-modifying statement, whose rows are its RETURNING list. The WITH
 * clause that holds the expression binds it (PG-COMMON-TABLE-001).
 * Source: https://www.postgresql.org/docs/17/queries-with.html.
 *
 * @visibility public
 * @example Reading a common table expression
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]);
 *     $table = new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('x'), $one, [new \SqlSemantics\Statement\Identifier\Name('a')], \SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization::Materialized);
 *     [$table->name->value, $table->columns[0]->value, $table->materialization->value] // => ['x', 'a', 'MATERIALIZED']
 */
final class CommonTableExpression implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The column names written after the name
     */
    public readonly array $columns;

    /**
     * @param Name $name The name the statement refers to the table by
     * @param Query $query The query or data-modifying statement that computes the rows
     * @param list<Name> $columns The column names written after the name
     * @param Materialization|null $materialization Whether the rows are computed once, as written
     * @param SearchClause|null $search The SEARCH clause of a recursive query
     * @param CycleClause|null $cycle The CYCLE clause of a recursive query
     */
    public function __construct(
        public readonly Name $name,
        public readonly Query $query,
        array $columns = [],
        public readonly ?Materialization $materialization = null,
        public readonly ?SearchClause $search = null,
        public readonly ?CycleClause $cycle = null,
    ) {
        Check::input($query instanceof Statement, 'A common table expression is computed by a statement.');
        $this->columns = Check::listOf($columns, Name::class, 'The columns of a common table expression are names.');
    }

    /**
     * Writes the name, the columns, AS, the materialization, the statement in parentheses and the SEARCH and CYCLE clauses.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
        if ($this->columns !== []) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
        $out->keyword('AS');
        if ($this->materialization !== null) {
            $out->keyword(...explode(' ', $this->materialization->value));
        }
        $out->symbol('(')->node($this->query)->symbol(')')->node($this->search)->node($this->cycle);
    }
}
