<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;

/**
 * One function call of a FROM item, with the column definitions written for it inside ROWS FROM.
 *
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLEFUNCTIONS.
 *
 * @visibility SqlSemantics
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
