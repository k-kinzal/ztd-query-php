<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use Fuzz\Structure\Context\Columns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Changes ambient column types while retaining a generated conversion unchanged.
 * A populated TIMESTAMP fixture distinguishes catalog-dependent input roles from
 * intrinsic conversion defects. Later conditions remain visible to the audit.
 */
final class TimeZoneHistory
{
    /**
     * @return list<M\Statement> Explicit populated column definitions before the unchanged retry
     */
    public function steps(M\Statement|Scalar $node, int $code): array
    {
        if ($code !== 3998 || !$node instanceof Scalar || array_filter((new Inputs())->values($node), static fn ($value): bool => $value instanceof M\Expression\Conversion\AtTimeZone) === []) {
            return [];
        }
        $steps = [];
        foreach ((new Columns())->bindings($node) as $binding) {
            $name = $binding['relation']->name;
            $elements = array_map(static fn (Name $column): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Temporal(M\Type\Kind\TemporalKind::Timestamp), [new M\Table\Column\DefaultLiteral(new M\Literal\StringLiteral(['2024-01-01 01:02:03']))])), $binding['columns']);
            $row = new M\Dml\Insert\InsertedRow(array_map(static fn (Name $column): M\Literal\StringLiteral => new M\Literal\StringLiteral(['2024-01-01 01:02:03']), $binding['columns']));
            array_push($steps, new M\Alter\DropTable(false, false, [new M\Alter\TargetTable($name)]), new M\Table\CreateTable($name, $elements), new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($name)), [$row]));
        }
        return $steps;
    }
}
