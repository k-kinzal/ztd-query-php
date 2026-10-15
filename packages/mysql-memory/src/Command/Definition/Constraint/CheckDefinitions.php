<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Expression\ExpressionRole;
use MySqlMemory\Command\Definition\Expression\ExpressionRules;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;

/**
 * Names CHECK constraints and checks their expression forms before compiling them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility MySqlMemory
 */
final class CheckDefinitions
{
    /**
     * Answers each CHECK constraint the statement writes, in written order: the constraint, the position of the column it follows or null, its name, whether the server named it, and whether it is enforced.
     *
     * @return list<array{CheckConstraint, int|null, string, bool, bool}>
     */
    public static function written(CreateTable $create, bool $legacy, int $checkBase = 0): array
    {
        if ($legacy) {
            return [];
        }
        $table = $create->name->name->value;
        $found = [];
        $position = 0;
        foreach ($create->elements as $element) {
            if ($element instanceof CheckConstraint) {
                $found[] = [$element, null, $element->enforced ?? true];
            }
            if (!$element instanceof ColumnElement) {
                continue;
            }
            foreach ($element->specification->columnAttributes() as $attribute) {
                if ($attribute instanceof CheckConstraint) {
                    $found[] = [$attribute, $position, $attribute->enforced ?? true];
                }
                if ($attribute instanceof EnforcementAttribute && $found !== [] && $found[count($found) - 1][1] === $position) {
                    $found[count($found) - 1][2] = $attribute->enforced;
                }
            }
            $position++;
        }
        $number = $checkBase;
        $named = [];
        foreach ($found as [$check, $column, $enforced]) {
            $name = $check->name?->name?->column->value;
            $named[] = [$check, $column, $name ?? $table . '_chk_' . ++$number, $name === null || Constraints::generated($name, $table, '_chk_'), $enforced !== false];
        }

        return $named;
    }

    /**
     * Refuses a CHECK constraint name used twice, then the first constraint that is not a condition or holds what a constraint may not.
     *
     * @throws SqlError When a constraint is refused
     */
    public static function forbidden(CreateTable $create, bool $legacy, int $checkBase = 0): void
    {
        $seen = [];
        $written = self::written($create, $legacy, $checkBase);
        foreach ($written as [, , $name]) {
            if (isset($seen[mb_strtolower($name)])) {
                throw ConstraintError::DuplicateCheckName->error($name);
            }
            $seen[mb_strtolower($name)] = true;
        }
        $rules = new ExpressionRules();
        foreach ($written as [$check, , $name]) {
            $rules->condition($check->condition, $name);
            $rules->forbidden($check->condition, ExpressionRole::Check, $name);
        }
    }

}
