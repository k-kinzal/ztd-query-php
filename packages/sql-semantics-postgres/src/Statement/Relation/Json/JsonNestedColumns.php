<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Json;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * NESTED [PATH] path [AS name] COLUMNS (…): columns read from the items a path yields within the row.
 *
 * Mirrors PostgreSQL's `JsonTableColumn` of type JTC_NESTED. The PATH word
 * is optional and always written.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE.
 *
 * @visibility public
 * @example Reading nested columns
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2'))->analyze("SELECT * FROM JSON_TABLE ('[]', '$[*]' COLUMNS (NESTED '$.b[*]' COLUMNS (b text)))");
 *     [$query->field(0)->name->value, $query->toString()] // => ['b', "SELECT * FROM JSON_TABLE ('[]', '$[*]' COLUMNS (NESTED PATH '$.b[*]' COLUMNS (b text)))"]
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
