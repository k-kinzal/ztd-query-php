<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Constraint;

use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;

/**
 * Gives unnamed PostgreSQL constraints the names the server chooses when it creates them, so that later DROP
 * CONSTRAINT statements find them: table_pkey, table_columns_key, table_columns_fkey, table_column_check for a
 * check on one column and table_check for any other check. A name taken by a constraint of the schema, or for keys
 * by a relation, is numbered (table_a_check1), and every name is cut to 63 bytes as the server cuts it.
 *
 * @visibility SqlSemantics
 */
final class PostgreSqlConstraintNames
{
    /**
     * The longest identifier PostgreSQL keeps, in bytes (NAMEDATALEN - 1).
     */
    public const MAXIMUM_LENGTH = 63;

    /**
     * Names the unnamed constraints of every table; a name, once given, stays when the table or its columns are
     * renamed, as on the server.
     *
     * @param list<TableDefinition> $tables
     * @return list<TableDefinition>
     */
    public static function assign(array $tables): array
    {
        foreach ($tables as $position => $table) {
            if (array_filter($table->constraints, static fn (TableConstraint $constraint): bool => $constraint->name === null) === []) {
                continue;
            }
            $tables[$position] = self::table($table, ...self::taken($tables, $table->schema));
        }
        return $tables;
    }

    /**
     * Returns the constraint names and the relation names of one namespace, which unnamed constraints avoid.
     *
     * @param list<TableDefinition> $tables
     * @return array{list<string>, list<string>}
     */
    public static function taken(array $tables, string $schema): array
    {
        $constraints = [];
        $relations = [];
        foreach ($tables as $table) {
            if ($table->schema !== $schema) {
                continue;
            }
            $relations[] = $table->name;
            foreach ($table->constraints as $constraint) {
                if ($constraint->name !== null) {
                    $constraints[] = $constraint->name;
                }
            }
            foreach ($table->indexes as $index) {
                if ($index->name !== null) {
                    $relations[] = $index->name;
                }
            }
        }
        return [$constraints, $relations];
    }

    /**
     * Names the unnamed constraints of one table in the order the server creates them: checks, then the primary
     * key, then unique keys, then foreign keys, each in declaration order.
     *
     * @param list<string> $constraints Constraint names already taken in the namespace
     * @param list<string> $relations Relation names already taken in the namespace
     */
    public static function table(TableDefinition $table, array $constraints, array $relations): TableDefinition
    {
        $named = $table->constraints;
        foreach ([Constraint\Check::class, Constraint\PrimaryKey::class, Constraint\UniqueKey::class, Constraint\ForeignKey::class] as $class) {
            foreach ($named as $position => $constraint) {
                if ($constraint->name !== null || $constraint::class !== $class) {
                    continue;
                }
                [$addition, $label] = self::parts($constraint);
                $taken = $constraint instanceof Constraint\Check || $constraint instanceof Constraint\ForeignKey ? $constraints : [...$constraints, ...$relations];
                $name = self::choose($table->name, $addition, $label, $taken);
                $constraints[] = $name;
                if (!$constraint instanceof Constraint\Check && !$constraint instanceof Constraint\ForeignKey) {
                    $relations[] = $name;
                }
                $named[$position] = self::named($constraint, $name);
            }
        }
        return new TableDefinition($table->schema, $table->name, $table->columns, $named, $table->source, $table->resolved, $table->indexes, $table->properties);
    }

    /**
     * Returns the column part and the label of the name the server derives for a constraint: a check names its
     * column only when its expression reads exactly one, a key names its key and included columns, a primary key
     * names none.
     *
     * @return array{?string, string}
     */
    public static function parts(TableConstraint $constraint): array
    {
        if ($constraint instanceof Constraint\Check) {
            $columns = array_values(array_unique(array_map(static fn (\SqlSemantics\Model\ColumnBinding $binding): string => $binding->column->name, $constraint->predicate->lineage())));
            return [count($columns) === 1 ? $columns[0] : null, 'check'];
        }
        if ($constraint instanceof Constraint\PrimaryKey) {
            return [null, 'pkey'];
        }
        if ($constraint instanceof Constraint\UniqueKey) {
            return [self::addition(self::indexColumns([...$constraint->localColumns(), ...$constraint->index->include])), 'key'];
        }
        return [self::addition($constraint->localColumns()), 'fkey'];
    }

    /**
     * Returns the first of name, name1, name2, ... that no taken name uses.
     *
     * @param list<string> $taken
     */
    public static function choose(string $table, ?string $addition, string $label, array $taken): string
    {
        $pass = 0;
        do {
            $name = self::objectName($table, $addition, $pass === 0 ? $label : $label . $pass);
            $pass++;
        } while (in_array($name, $taken, true));
        return $name;
    }

    /**
     * Joins the table name, the column part and the label with underscores, cutting the longer of the first two
     * until the name fits in 63 bytes, without splitting a character.
     */
    public static function objectName(string $table, ?string $addition, string $label): string
    {
        $available = self::MAXIMUM_LENGTH - strlen($label) - 1 - ($addition === null ? 0 : 1);
        $first = strlen($table);
        $second = $addition === null ? 0 : strlen($addition);
        while ($first + $second > $available) {
            if ($first > $second) {
                $first--;
            } else {
                $second--;
            }
        }
        $parts = [mb_strcut($table, 0, $first, 'UTF-8')];
        if ($addition !== null) {
            $parts[] = mb_strcut($addition, 0, $second, 'UTF-8');
        }
        $parts[] = $label;
        return implode('_', $parts);
    }

    /**
     * Joins column names with underscores, stopping once the result reaches 64 bytes.
     *
     * @param list<string> $columns
     */
    public static function addition(array $columns): string
    {
        $result = '';
        foreach ($columns as $column) {
            $result .= ($result === '' ? '' : '_') . $column;
            if (strlen($result) > self::MAXIMUM_LENGTH) {
                break;
            }
        }
        return $result;
    }

    /**
     * Returns the names of the index columns, numbering a name an earlier column already uses (a, a1).
     *
     * @param list<string> $columns
     * @return list<string>
     */
    public static function indexColumns(array $columns): array
    {
        $result = [];
        foreach ($columns as $column) {
            $name = $column;
            for ($number = 1; in_array($name, $result, true); $number++) {
                $name = mb_strcut($column, 0, self::MAXIMUM_LENGTH - strlen((string) $number), 'UTF-8') . $number;
            }
            $result[] = $name;
        }
        return $result;
    }

    /**
     * Returns the constraint with the given name, or unnamed for null, and every other operand unchanged.
     */
    public static function named(TableConstraint $constraint, ?string $name): TableConstraint
    {
        return match (true) {
            $constraint instanceof Constraint\Check => new Constraint\Check($constraint->predicate, $constraint->enforced, $constraint->noInherit, $name, $constraint->source, $constraint->onConflict),
            $constraint instanceof Constraint\PrimaryKey => new Constraint\PrimaryKey($constraint->keys, $constraint->checking, $constraint->nullsDistinct, $name, $constraint->source, $constraint->onConflict, $constraint->index),
            $constraint instanceof Constraint\UniqueKey => new Constraint\UniqueKey($constraint->keys, $constraint->checking, $constraint->nullsDistinct, $name, $constraint->source, $constraint->onConflict, $constraint->index),
            $constraint instanceof Constraint\ForeignKey => new Constraint\ForeignKey($constraint->columns, $constraint->referencedTable, $constraint->referencedColumns, $constraint->onDelete, $constraint->onUpdate, $constraint->match, $constraint->checking, $constraint->deleteColumns, $name, $constraint->source, $constraint->indexName),
            default => $constraint,
        };
    }

    /**
     * Returns the constraints a table receives from a parent it inherits (its inheritable checks, which keep their
     * names) or from a LIKE template (its checks with their names and its keys, which the new table names afresh);
     * foreign keys are never copied.
     *
     * @param list<TableConstraint> $constraints
     * @return list<TableConstraint>
     */
    public static function copied(array $constraints, bool $inherited): array
    {
        $result = [];
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Constraint\Check && !$constraint->noInherit) {
                $result[] = $constraint;
            } elseif (!$inherited && ($constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey)) {
                $result[] = self::named($constraint, null);
            }
        }
        return $result;
    }
}
