<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\With;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;

/**
 * One common table expression: a named query, optionally with column names and a materialization hint.
 *
 * Source: https://sqlite.org/lang_with.html.
 *
 * @visibility SqlSemantics
 */
final class CommonTable implements Node
{
    use Snapshot;

    /**
     * @var list<ListedColumn> The column names in written order
     */
    public readonly array $columns;

    /**
     * @param Name $name The table name
     * @param Query $query The query
     * @param list<ListedColumn> $columns The column names; none when the list is absent
     * @param Materialization|null $materialization The written hint
     */
    public function __construct(public readonly Name $name, public readonly Query $query, array $columns = [], public readonly ?Materialization $materialization = null)
    {
        $this->columns = Check::listOf($columns, ListedColumn::class, 'The column list of a common table holds column names.');
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Relation);
        if ($this->columns !== []) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
        $out->keyword('AS');
        if ($this->materialization !== null) {
            $out->keyword(...explode(' ', $this->materialization->value));
        }
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
