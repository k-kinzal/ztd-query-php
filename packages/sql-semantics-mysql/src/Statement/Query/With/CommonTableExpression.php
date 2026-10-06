<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\With;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * One common table expression: `name [(columns)] AS (query)`.
 *
 * The query is the one inside the parentheses the syntax requires. The WITH
 * clause that holds the expression derives it (MYSQL-WITH-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/with.html.
 *
 * @visibility public
 * @example Reading a common table expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('WITH c (x) AS (SELECT 1) SELECT x FROM c');
 *     [$query->statement->with->tables[0]->name->value, $query->statement->with->tables[0]->columns[0]->value] // => ['c', 'x']
 */
final class CommonTableExpression implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The column names in written order; empty when the query names the columns
     */
    public readonly array $columns;

    /**
     * @param Name $name The table name
     * @param list<Name> $columns The column names in written order
     * @param Query $query The query that produces the rows
     */
    public function __construct(public readonly Name $name, array $columns, public readonly Query $query)
    {
        $this->columns = Check::listOf($columns, Name::class, 'The column list of a common table expression holds names.');
    }

    /**
     * Writes the expression.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Relation);
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
        $out->keyword('AS')->symbol('(')->node($this->query)->symbol(')');
    }
}
