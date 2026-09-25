<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;

/**
 * Checks storage compatibility without confusing runtime conversion with static certainty.
 *
 * @visibility SqlSemantics
 */
final class AssignmentRules
{
    /**
     * Diagnoses known type and NOT NULL violations while preserving the original value.
     */
    public function check(Expression $target, Expression|\SqlSemantics\Model\Write\DefaultSource $value, Scope $scope, bool $storing = true): void
    {
        $this->checkPath(StoragePathBinder::bind($target), $value, $scope, $storing);
    }

    /**
     * Checks a typed writable location without loading or evaluating values.
     */
    public function checkPath(\SqlSemantics\Model\Write\Storage\Path $target, Expression|\SqlSemantics\Model\Write\DefaultSource $value, Scope $scope, bool $storing = true): void
    {
        $column = $target->column();
        if ($column->columnBinding() === null || $value instanceof \SqlSemantics\Model\Write\DefaultSource) {
            return;
        }
        if ($storing && $target instanceof \SqlSemantics\Model\Write\Storage\ColumnPath && $column->columnBinding()->column->nullability === Nullability::NotNull && $value->nullability === Nullability::AlwaysNull) {
            $scope->diagnostics()->report('null-assignment', 'NULL cannot be stored in a NOT NULL column.', $value->source);
        }
        $this->checkType($target->type(), $value, $scope);
    }

    /**
     * Checks declared storage compatibility without requiring a pre-existing column binding.
     */
    public function checkType(\SqlSemantics\Type\TypeDescriptor $target, Expression $value, Scope $scope): void
    {
        $targetName = $target->name;
        $sourceName = $value->type->name;
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || $sourceName === 'unknown' || $targetName === 'unknown' || $targetName === $sourceName) {
            return;
        }
        $numeric = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision'];
        $text = ['text', 'varchar', 'char', 'bpchar'];
        if ((in_array($targetName, $numeric, true) && in_array($sourceName, $numeric, true)) || in_array($targetName, $text, true)) {
            return;
        }
        $builtin = [...$numeric, ...$text, 'boolean', 'date', 'timestamp', 'timestamp with time zone', 'interval'];
        if (in_array($sourceName, $builtin, true) && in_array($targetName, $builtin, true)) {
            $scope->diagnostics()->report('incompatible-assignment', 'Cannot assign ' . $sourceName . ' to ' . $targetName . '.', $value->source);
        }
    }
}
