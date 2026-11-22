<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Json;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * NESTED [PATH] path [AS name] COLUMNS (…): columns read from the items a path yields within the row.
 *
 * Mirrors PostgreSQL's `JsonTableColumn` of type JTC_NESTED. The PATH word
 * is optional and always written.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE.
 *
 * @visibility SqlSemantics
 */
final class JsonNestedColumns implements JsonTableColumn
{
    use Snapshot;

    /**
     * @var non-empty-list<JsonTableColumn> The nested columns in written order
     */
    public readonly array $columns;

    /**
     * @param StringConstant $path The path of the nested items
     * @param list<JsonTableColumn> $columns The nested columns in written order; at least one
     * @param Name|null $pathName The name of the path
     */
    public function __construct(public readonly StringConstant $path, array $columns, public readonly ?Name $pathName = null)
    {
        $this->columns = Check::listOf($columns, JsonTableColumn::class, 'NESTED holds at least one column.', 1);
    }

    /**
     * Writes NESTED PATH, the path, its name and the columns.
     */
    public function render(Output $out): void
    {
        $out->keyword('NESTED', 'PATH')->node($this->path);
        if ($this->pathName !== null) {
            $out->keyword('AS')->name($this->pathName, NameUse::Column);
        }
        $out->keyword('COLUMNS')->symbol('(')->list($this->columns)->symbol(')');
    }
}
