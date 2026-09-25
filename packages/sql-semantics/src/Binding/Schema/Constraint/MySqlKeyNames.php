<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Constraint;

use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Gives unnamed MySQL keys the names the server chooses when it creates them, so that later DROP INDEX statements
 * find them: a key is named after its first column, or functional_index for an expression, and a name that PRIMARY
 * or a key created before it already uses, compared without case, takes the first free suffix of _2 to _99.
 *
 * @visibility SqlSemantics
 */
final class MySqlKeyNames
{
    /**
     * The name every MySQL primary key has, which no other key can take.
     */
    public const PRIMARY = 'PRIMARY';

    /**
     * The name a key whose first part is an expression is named after.
     */
    public const FUNCTIONAL = 'functional_index';

    /**
     * Names the unnamed unique keys and indexes of every table in the order the statements wrote them; a name, once
     * given, stays when the key's column is renamed, as on the server.
     *
     * @param list<TableDefinition> $tables
     * @return list<TableDefinition>
     */
    public static function assign(array $tables): array
    {
        return array_map(self::table(...), $tables);
    }

    /**
     * Names the unnamed keys of one table against the names its keys already have.
     */
    public static function table(TableDefinition $table): TableDefinition
    {
        $taken = [self::PRIMARY];
        $pending = [];
        foreach ($table->constraints as $position => $constraint) {
            if (!$constraint instanceof Constraint\UniqueKey) {
                continue;
            }
            $name = $constraint->index->name ?? $constraint->name;
            if ($name === null) {
                $pending[] = [true, $position, self::offset($constraint->source), self::base($constraint->keys)];
            } else {
                $taken[] = $name;
            }
        }
        foreach ($table->indexes as $position => $index) {
            if ($index->name === null) {
                $pending[] = [false, $position, self::offset($index->source), self::base($index->elements)];
            } else {
                $taken[] = $index->name;
            }
        }
        if ($pending === []) {
            return $table;
        }
        usort($pending, static fn (array $left, array $right): int => $left[2] <=> $right[2]);
        $constraints = $table->constraints;
        $indexes = $table->indexes;
        foreach ($pending as [$constraint, $position, , $base]) {
            $name = self::choose($base, $taken);
            $taken[] = $name;
            if ($constraint) {
                $constraints[$position] = PostgreSqlConstraintNames::named($constraints[$position], $name);
            } else {
                $indexes[$position] = self::index($indexes[$position], $name);
            }
        }
        return new TableDefinition($table->schema, $table->name, $table->columns, array_values($constraints), $table->source, $table->resolved, array_values($indexes), $table->properties);
    }

    /**
     * Returns the name a key starts from: its first column, or functional_index when its first part is an expression.
     *
     * @param list<\SqlSemantics\Schema\IndexElement> $keys
     */
    public static function base(array $keys): string
    {
        $first = $keys[0] ?? null;
        $name = $first === null ? '' : MySqlCounterKeys::name($first);
        return $name === '' ? self::FUNCTIONAL : $name;
    }

    /**
     * Returns the base name when it is free, or else the first free of base_2 to base_99.
     *
     * @param list<string> $taken
     */
    public static function choose(string $base, array $taken): string
    {
        $used = array_map(strtolower(...), $taken);
        if (!in_array(strtolower($base), $used, true)) {
            return $base;
        }
        for ($number = 2; $number < 100; $number++) {
            $name = mb_strcut($base, 0, 61, 'UTF-8') . '_' . $number;
            if (!in_array(strtolower($name), $used, true)) {
                return $name;
            }
        }
        return 'not_specified';
    }

    /**
     * Returns the index with the given name and every other operand unchanged.
     */
    public static function index(IndexDefinition $index, string $name): IndexDefinition
    {
        return new IndexDefinition($index->schema, $name, $index->table, $index->elements, $index->unique, $index->method, $index->include, $index->predicate, $index->source, $index->properties);
    }

    /**
     * Returns where a key is written, which orders the keys one statement creates.
     */
    public static function offset(\SqlParser\Parser\Node $source): int
    {
        return $source->span()[0] ?? PHP_INT_MAX;
    }
}
