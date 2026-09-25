<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Constraint;

use SqlParser\Parser\Node;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Column\AutoIncrementColumn;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\Index\ColumnKey;
use SqlSemantics\Schema\TableDefinition;

/**
 * Checks the MySQL rule for AUTO_INCREMENT columns (error 1075): a table has at most one, and a key of the table must
 * begin with it; MyISAM, MERGE and BLACKHOLE tables also accept it at a later position of a key. A primary key, a
 * unique key, an index, and the index a foreign key creates all count; an expression key does not.
 *
 * @visibility SqlSemantics
 */
final class MySqlCounterKeys
{
    /**
     * The storage engines that keep one counter for each value of the key parts before the AUTO_INCREMENT column.
     */
    public const GROUPED_ENGINES = ['MYISAM', 'MRG_MYISAM', 'MERGE', 'BLACKHOLE'];

    /**
     * Rejects a table whose AUTO_INCREMENT columns break the rule.
     *
     * @param Node $source The statement that gives the table this shape
     * @throws InvalidSql
     */
    public static function check(TableDefinition $table, Node $source): void
    {
        $counters = array_values(array_filter($table->columns, static fn ($column): bool => $column->generation instanceof AutoIncrementColumn));
        if (isset($counters[1])) {
            throw new InvalidSql(InputViolation::AutoIncrementKey, $source);
        }
        if (!isset($counters[0])) {
            return;
        }
        $engine = $table->properties instanceof \SqlSemantics\Schema\Table\MySqlProperties ? strtoupper($table->properties->engine ?? '') : '';
        $grouped = in_array($engine, self::GROUPED_ENGINES, true);
        foreach (self::keys($table) as $columns) {
            $position = array_search(strtolower($counters[0]->name), array_map(strtolower(...), $columns), true);
            if ($position === 0 || $grouped && $position !== false) {
                return;
            }
        }
        throw new InvalidSql(InputViolation::AutoIncrementKey, $source);
    }

    /**
     * Returns the column names of every key of the table in key order; an expression part is an empty name.
     *
     * @return list<list<string>>
     */
    public static function keys(TableDefinition $table): array
    {
        $keys = [];
        foreach ($table->constraints as $constraint) {
            if ($constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey) {
                $keys[] = array_map(self::name(...), $constraint->keys);
            } elseif ($constraint instanceof Constraint\ForeignKey) {
                $keys[] = $constraint->localColumns();
            }
        }
        foreach ($table->indexes as $index) {
            $keys[] = array_map(self::name(...), $index->elements);
        }
        return $keys;
    }

    /**
     * Returns the column a key part names, or an empty name for an expression part.
     */
    public static function name(\SqlSemantics\Schema\IndexElement $key): string
    {
        return $key instanceof ColumnKey ? ($key->column->columnBinding()?->column->name ?? $key->column->referenceParts()[0] ?? '') : '';
    }
}
