<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint;

/**
 * Resolves each constraint against the table being declared.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CheckLowering
{
    /**
     * Collects checks in native evaluation order.
     * @param non-empty-list<TableColumn> $columns
     * @return list<CheckConstraint>
     */
    public function lower(BoundStatement $operation, CreateTable $definition, array $columns): array
    {
        $names = [];
        foreach ($columns as $column) {
            $names[] = $column->name;
        }
        $binding = new SourceBinding($operation, $definition, $names);
        $checks = [];
        foreach ($definition->columns as $slot => $column) {
            array_push($checks, ...$this->collect($binding, $column->constraints, $slot));
        }
        foreach ($definition->constraints as $run) {
            array_push($checks, ...$this->collect($binding, $run->items, null));
        }

        return $checks;
    }

    /**
     * Applies a decoded label within its column or comma-separated table run.
     * @param list<ColumnConstraint|TableConstraint> $constraints
     * @return list<CheckConstraint>
     * @throws InvalidConstruction When a condition has no complete representation
     */
    public function collect(SourceBinding $binding, array $constraints, ?int $owner): array
    {
        $label = null;
        $checks = [];
        foreach ($constraints as $constraint) {
            if ($constraint instanceof ConstraintName) {
                $label = $constraint->name;
            }
            if ($constraint instanceof ColumnCheck || $constraint instanceof TableCheck) {
                $condition = (new ExpressionLowering())->lower($constraint->expression, $binding);
                Check::input(!$condition instanceof Parameter, 'A schema expression cannot contain a parameter.');
                $name = $label === null
                    ? (new ImplicitCheckLowering())->label($condition instanceof Comparison ? $condition : null, $constraint->labelLayout)
                    : new Identifier($label->value);
                $checks[] = new CheckConstraint($name, $condition, $owner);
            }
        }

        return $checks;
    }
}
