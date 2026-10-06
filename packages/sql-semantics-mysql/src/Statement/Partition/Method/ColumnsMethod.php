<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `RANGE COLUMNS (columns)` or `LIST COLUMNS (columns)`: partitioning by a tuple of column values.
 *
 * Mirrors PT_part_type_def_range_columns and PT_part_type_def_list_columns.
 * The grammar of 5.6 and 5.7 also accepts an empty list, which the server
 * rejects; it is kept. A column name the completely known table does not
 * have is a diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-columns.html.
 *
 * @visibility public
 * @example Holding a list partitioning by columns
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::List, [new \SqlSemantics\Statement\Identifier\Name('region')]);
 *     [$method->kind->value, $method->columns[0]->value] // => ['LIST', 'region']
 */
final class ColumnsMethod implements PartitionMethod
{
    use Snapshot;

    /**
     * @var list<Name> The partitioning columns in order
     */
    public readonly array $columns;

    /**
     * @param PartitionKind $kind RANGE or LIST
     * @param list<Name> $columns The partitioning columns in order
     */
    public function __construct(public readonly PartitionKind $kind, array $columns)
    {
        $this->columns = Check::listOf($columns, Name::class, 'Partitioning columns are a list of names.');
    }

    /**
     * Reports the column names the completely known table does not have.
     */
    public function deriveMethod(Derivation $derivation, Environment $scope): void
    {
        (new ColumnNames())->check($this->columns, $derivation, $scope);
    }

    /**
     * Writes the method.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value, 'COLUMNS');
        (new ColumnNames())->write($out, $this->columns);
    }
}
