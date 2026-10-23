<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Expression\ExpressionRole;
use MySqlMemory\Command\Definition\Expression\ExpressionRules;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

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
    public static function written(CreateTable|DeferredCreateTable $create, bool $legacy, int $checkBase = 0): array
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
    public static function forbidden(CreateTable|DeferredCreateTable $create, bool $legacy, int $checkBase = 0, GrammarRelease $release = GrammarRelease::MySql847): void
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
        $columns = array_values(array_filter($create->elements, static fn ($element): bool => $element instanceof ColumnElement));
        foreach ($written as [$check, $column, $name]) {
            self::columnScope($check->condition, $column === null ? null : $columns[$column]->name->column, $name, $release);
            $rules->condition($check->condition, $name);
            $rules->forbidden($check->condition, ExpressionRole::Check, $name);
        }
    }

    /**
     * Checks the column-local reference domain before condition and function rules.
     *
     * MySQL 8.0 and 8.1 additionally require at least one reference. A table-level
     * constraint has no such restriction.
     *
     * @throws SqlError When a column constraint does not use only its owning column
     */
    public static function columnScope(Scalar $condition, ?Name $column, string $name, GrammarRelease $release): void
    {
        if ($column === null) {
            return;
        }
        $references = (new Walker())->find($condition, ColumnUse::class);
        if ($references === [] && in_array($release, [GrammarRelease::MySql8044, GrammarRelease::MySql810], true)) {
            throw ConstraintError::CheckOtherColumn->error($name);
        }
        foreach ($references as $reference) {
            if (!Comparison::AsciiInsensitive->equal($reference->name->value, $column->value)) {
                throw ConstraintError::CheckOtherColumn->error($name);
            }
        }
    }

}
