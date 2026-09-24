<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A nested path that expands rows and owns a required set of child columns.
 * @visibility public
 * @example Reading a nested path
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (NESTED PATH '$.c[*]' AS sub COLUMNS (v INTEGER PATH '$'))) AS j");
 *     [$statement->from->table->columns[0]->name, count($statement->from->table->columns[0]->columns)] // => ['sub', 1]
 */
final class NestedColumns implements Column
{
    /**
     * @var non-empty-list<Column> Child declarations
     */
    public readonly array $columns;

    /**
     * @param list<Column> $columns
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $path, array $columns, public readonly ?string $name = null)
    {
        Collections::objects($columns, Column::class);
        if ($columns === []) {
            throw new InvalidStructure('A nested JSON path requires child columns.');
        }
        $this->columns = $columns;
    }
}
