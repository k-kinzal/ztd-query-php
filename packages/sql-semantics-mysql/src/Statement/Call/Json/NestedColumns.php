<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;

/**
 * A `NESTED PATH path COLUMNS (...)` column of JSON_TABLE: the columns of the rows of a nested array.
 *
 * Rule: MYSQL-JSON-TABLE-COLUMN-001 (nested). The nested columns are part of
 * the output of JSON_TABLE in written order; they are NULL in a row for
 * which the nested path finds nothing. Terminates: the nested columns are
 * strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the nested columns
 *     $nested = new \SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$.b[*]']), [new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('n'))]);
 *     count($nested->columns) // => 1
 */
final class NestedColumns implements JsonTableColumn
{
    use Snapshot;

    /**
     * @var list<JsonTableColumn> The nested columns in order
     */
    public readonly array $columns;

    /**
     * @param StringLiteral $path The path of the nested array
     * @param list<JsonTableColumn> $columns The nested columns in order; at least one
     */
    public function __construct(public readonly StringLiteral $path, array $columns)
    {
        $this->columns = Check::listOf($columns, JsonTableColumn::class, 'A nested path has at least one column.', 1);
    }

    /**
     * Derives the path and the nested columns and answers their output, which can be NULL.
     *
     * @return list<OutputSlot>
     */
    public function deriveColumns(Derivation $derivation, Environment $environment): array
    {
        (new Arguments())->one($this->path, $derivation, $environment);
        $slots = [];
        foreach ($this->columns as $column) {
            foreach ($column->deriveColumns($derivation, $environment) as $slot) {
                $slots[] = new OutputSlot($slot->name, $slot->type, \SqlSemantics\Statement\Type\Nullability::Nullable);
            }
        }

        return $slots;
    }

    /**
     * Writes the nested path and its columns.
     */
    public function render(Output $out): void
    {
        $out->keyword('NESTED', 'PATH')->node($this->path)->keyword('COLUMNS')->symbol('(')->list($this->columns)->symbol(')');
    }
}
