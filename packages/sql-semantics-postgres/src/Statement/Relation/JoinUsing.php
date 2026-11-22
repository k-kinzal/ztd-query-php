<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;

/**
 * USING (columns) [AS alias]: a join on equal columns of both sides, merged into one column each.
 *
 * Mirrors the `usingClause` and `join_using_alias` of PostgreSQL's
 * `JoinExpr`. The alias names the merged columns only.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN.
 *
 * @visibility SqlSemantics
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
