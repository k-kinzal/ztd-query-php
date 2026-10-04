<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One function call of a FROM item, with the column definitions written for it inside ROWS FROM.
 *
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLEFUNCTIONS.
 *
 * @visibility public
 * @example Reading the function of a FROM item
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT * FROM ROWS FROM (f() AS (a integer))');
 *     $query->statement->from->functions[0]->definitions[0]->name->value // => 'a'
 */
final class TableFunction implements Node
{
    use Snapshot;

    /**
     * @var list<TypedColumn> The column definitions written after AS
     */
    public readonly array $definitions;

    /**
     * @param Scalar $call The function call
     * @param list<TypedColumn> $definitions The column definitions written after AS
     */
    public function __construct(public readonly Scalar $call, array $definitions = [])
    {
        $this->definitions = Check::listOf($definitions, TypedColumn::class, 'Column definitions are typed columns.');
    }

    /**
     * Writes the call and the column definitions.
     */
    public function render(Output $out): void
    {
        $out->node($this->call);
        if ($this->definitions !== []) {
            $out->keyword('AS')->symbol('(')->list($this->definitions)->symbol(')');
        }
    }
}
