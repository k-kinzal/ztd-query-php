<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Expression\ExpressionRole;
use MySqlMemory\Command\Definition\Expression\ExpressionRules;
use MySqlMemory\Command\Definition\Expression\ItemText;
use MySqlMemory\Command\Definition\Expression\TableExpressions;
use MySqlMemory\Dictionary\Check;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;

/**
 * Builds the CHECK constraints and foreign keys a CREATE TABLE statement declares.
 *
 * A CHECK constraint is written as a table element, or after a column, where it may read only
 * that column. One without a name is named `<table>_chk_<n>`, numbered in written order among the
 * unnamed ones; a name is used once. The condition must be a condition, may not hold a
 * subquery, a variable or a function whose result depends on more than its arguments, and may
 * not read an unknown or AUTO_INCREMENT column. The constraints are kept in the order of their
 * names, which SHOW CREATE TABLE writes and the server checks them in. MySQL 5.6 and 5.7 read
 * CHECK constraints and ignore them (verified on live 5.6.51, 5.7.44 and 8.4 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility MySqlMemory
 */
final class Constraints
{
    /**
     * @param Planner $planner The planner of the statement, which compiles the conditions
     * @param CreateTable $create The statement that declares the table
     * @param int $checkBase The number the first unnamed CHECK constraint is numbered after
     * @param int $foreignBase The number the first unnamed foreign key is numbered after
     * @param array<string, true> $kept The foreign keys, by lowercase name, a changed table had, which are not checked against the table they reference again
     */
    public function __construct(public readonly Planner $planner, public readonly CreateTable $create, public readonly int $checkBase = 0, public readonly int $foreignBase = 0, public readonly array $kept = [])
    {
    }

    /**
     * Answers each CHECK constraint the statement writes, in written order: the constraint, the position of the column it follows or null, its name, whether the server named it, and whether it is enforced.
     *
     * @return list<array{CheckConstraint, int|null, string, bool, bool}>
     */
    public function written(): array
    {
        if ($this->planner->settings->legacy()) {
            return [];
        }
        $table = $this->create->name->name->value;
        $found = [];
        $position = 0;
        foreach ($this->create->elements as $element) {
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
        $number = $this->checkBase;
        $named = [];
        foreach ($found as [$check, $column, $enforced]) {
            $name = $check->name?->name?->column->value;
            $named[] = [$check, $column, $name ?? $table . '_chk_' . ++$number, $name === null || self::generated($name, $table, '_chk_'), $enforced !== false];
        }

        return $named;
    }

    /**
     * Tells whether a constraint name is one the server gives: the table name, a suffix and a number.
     */
    public static function generated(string $name, string $table, string $suffix): bool
    {
        return preg_match('/\A' . preg_quote(mb_strtolower($table . $suffix), '/') . '[0-9]+\z/u', mb_strtolower($name)) === 1;
    }

    /**
     * Answers the highest number the server gave a constraint named after a table with a suffix, or 0.
     *
     * @param list<string> $names
     */
    public static function highest(array $names, string $table, string $suffix): int
    {
        $highest = 0;
        foreach ($names as $name) {
            if (self::generated($name, $table, $suffix)) {
                $highest = max($highest, (int) substr($name, mb_strlen($table . $suffix)));
            }
        }

        return $highest;
    }

    /**
     * Refuses a CHECK constraint name used twice, then the first constraint that is not a condition or holds what a constraint may not.
     *
     * @throws SqlError When a constraint is refused
     */
    public function forbidden(): void
    {
        $seen = [];
        $written = $this->written();
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

    /**
     * Refuses a CHECK constraint or foreign key named as one of another table of the database: the names of each kind are unique in a database.
     *
     * @param StoredTable|null $replaced The table the definition replaces, whose names are free
     *
     * @throws SqlError When a name is taken
     */
    public static function unique(TableDefinition $definition, Schema $schema, ?StoredTable $replaced = null): void
    {
        $checks = [];
        $keys = [];
        foreach ($schema->tables as $table) {
            if ($table === $replaced) {
                continue;
            }
            foreach ($table->definition->checks as $check) {
                $checks[mb_strtolower($check->name)] = true;
            }
            foreach ($table->definition->foreignKeys as $key) {
                $keys[mb_strtolower($key->name)] = true;
            }
        }
        foreach ($definition->foreignKeys as $key) {
            if (isset($keys[mb_strtolower($key->name)])) {
                throw ConstraintError::DuplicateForeignName->error($key->name);
            }
        }
        foreach ($definition->checks as $check) {
            if (isset($checks[mb_strtolower($check->name)])) {
                throw ConstraintError::DuplicateCheckName->error($check->name);
            }
        }
    }

    /**
     * Answers a definition with its CHECK constraints compiled and its foreign keys.
     *
     * @throws SqlError When a constraint reads what it may not, or a foreign key is invalid
     */
    public function constrained(TableDefinition $definition): TableDefinition
    {
        $expressions = new TableExpressions($this->planner, $this->create);
        $scope = $expressions->scope($definition);
        $text = new ItemText([], $this->planner->settings->connectionCollation->charset->name, $this->planner->settings->release());
        $checks = [];
        foreach ($this->written() as [$check, $column, $name, $generated, $enforced]) {
            $references = $expressions->references($check->condition, $definition->columns);
            foreach ($references as $reference) {
                if ($column !== null && $reference !== $column) {
                    throw ConstraintError::CheckOtherColumn->error($name);
                }
                if (is_string($reference)) {
                    throw ConstraintError::CheckColumnMissing->error($name, $reference);
                }
                if ($definition->columns[$reference]->autoIncrement) {
                    throw ConstraintError::CheckAutoIncrement->error($name);
                }
            }
            (new ExpressionRules())->grouped($check->condition);
            $positions = array_values(array_unique(array_filter($references, is_int(...))));
            $checks[] = new Check($name, $expressions->compiled($check->condition, $scope), $enforced, $text->text($check->condition), $generated, $positions, $check->condition);
        }
        usort($checks, static fn (Check $left, Check $right): int => strcmp($left->name, $right->name));
        $keys = (new ForeignKeys($this->planner, $this->create, $this->foreignBase, $this->kept))->built($definition);
        $this->acted($checks, $keys, $definition);

        return $definition->withConstraints($checks, $keys);
    }

    /**
     * Refuses a CHECK constraint that reads a column a referential action of a foreign key changes: ON UPDATE CASCADE, SET NULL or SET DEFAULT, or ON DELETE SET NULL or SET DEFAULT (verified on a live 8.4 server).
     *
     * @param list<Check> $checks
     * @param list<\MySqlMemory\Dictionary\ForeignKey> $keys
     *
     * @throws SqlError When one does
     */
    public function acted(array $checks, array $keys, TableDefinition $definition): void
    {
        $changing = [ReferenceOption::Cascade, ReferenceOption::SetNull, ReferenceOption::SetDefault];
        foreach ($checks as $check) {
            foreach ($keys as $key) {
                if (!in_array($key->onUpdate, $changing, true) && !in_array($key->onDelete, [ReferenceOption::SetNull, ReferenceOption::SetDefault], true)) {
                    continue;
                }
                foreach (array_intersect($check->columns, $key->columns) as $position) {
                    throw ConstraintError::CheckReferentialColumn->error($definition->columns[$position]->name, $check->name, $key->name);
                }
            }
        }
    }
}
