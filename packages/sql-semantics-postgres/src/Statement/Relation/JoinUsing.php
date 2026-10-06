<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * USING (columns) [AS alias]: a join on equal columns of both sides, merged into one column each.
 *
 * Mirrors the `usingClause` and `join_using_alias` of PostgreSQL's
 * `JoinExpr`. The alias names the merged columns only.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN.
 *
 * @visibility public
 * @example Reading a USING clause with an alias
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM a JOIN b USING (id) AS j');
 *     [$query->statement->from->condition->columns[0]->value, $query->statement->from->condition->alias->value] // => ['id', 'j']
 */
final class JoinUsing implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The merged columns in written order
     */
    public readonly array $columns;

    /**
     * @param list<Name> $columns The merged columns in written order; at least one
     * @param Name|null $alias The name the merged columns are qualified with
     */
    public function __construct(array $columns, public readonly ?Name $alias = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'USING names at least one column.', 1);
    }

    /**
     * Writes USING, the columns and the alias.
     */
    public function render(Output $out): void
    {
        $out->keyword('USING');
        (new AliasSpelling())->names($out, $this->columns);
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
    }
}
