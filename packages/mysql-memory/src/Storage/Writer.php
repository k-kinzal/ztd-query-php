<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Writes rows into a stored table, keeping its unique keys and its AUTO_INCREMENT counter.
 *
 * A row whose values equal those of a row of the table in every column of a unique key, none
 * of them NULL, conflicts with it (ER_DUP_ENTRY).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html.
 *
 * @visibility MySqlMemory
 */
final class Writer
{
    /**
     * @param StoredTable $table The table written
     * @param Context $context The statement writing
     */
    public function __construct(public readonly StoredTable $table, public readonly Context $context)
    {
    }

    /**
     * Answers the number of the first row a row conflicts with in a unique key, and the key; null when none.
     *
     * @param list<int|float|string|null> $row
     * @return array{int, Key}|null
     */
    public function conflict(array $row, ?int $except = null): ?array
    {
        $definition = $this->table->definition;
        foreach ($definition->keys as $key) {
            if (!$key->unique()) {
                continue;
            }
            $wanted = $this->key($row, $key);
            if ($wanted === null) {
                continue;
            }
            foreach ($this->table->data->rows as $number => $existing) {
                if ($number !== $except && $this->key($existing, $key) === $wanted) {
                    return [$number, $key];
                }
            }
        }

        return null;
    }

    /**
     * Answers the comparison key of the columns of a key in a row, or null when one is NULL.
     *
     * @param list<int|float|string|null> $row
     */
    public function key(array $row, Key $key): ?string
    {
        $text = '';
        foreach ($key->columns as $index => $position) {
            $value = $row[$position];
            if ($value === null) {
                return null;
            }
            $domain = $this->table->definition->columns[$position]->domain;
            $prefix = $key->prefixes[$index] ?? null;
            if ($prefix !== null && $domain->kind === Kind::String) {
                $value = mb_substr((string) $value, 0, $prefix, 'UTF-8');
            }
            $text .= Order::key($value, $domain) . "\0";
        }

        return $text;
    }

    /**
     * Answers the error of a conflict in a key.
     *
     * @param list<int|float|string|null> $row
     */
    public function duplicate(array $row, Key $key): SqlError
    {
        return ErrorCode::DuplicateEntry->error(...$this->entry($row, $key));
    }

    /**
     * Answers the entry and key name ER_DUP_ENTRY names for a conflict.
     *
     * @param list<int|float|string|null> $row
     * @return array{string, string}
     */
    public function entry(array $row, Key $key): array
    {
        $values = [];
        foreach ($key->columns as $position) {
            $values[] = (string) Convert::toText($row[$position], $this->table->definition->columns[$position]->domain);
        }

        return [mb_strcut(implode('-', $values), 0, 64, 'UTF-8'), $this->table->definition->name . '.' . $key->name];
    }

    /**
     * Fills the AUTO_INCREMENT column of a row that names no value, or advances the counter past the value it names.
     *
     * @param list<int|float|string|null> $row
     * @return array{list<int|float|string|null>, int|null} The row, and the value generated if any
     */
    public function autoIncrement(array $row, bool $noValueOnZero): array
    {
        $position = $this->table->definition->autoIncrementColumn();
        if ($position === null) {
            return [$row, null];
        }
        $data = $this->table->data;
        $value = $row[$position];
        if ($value === null || ($value === 0 && !$noValueOnZero) || ($value === 0.0 && !$noValueOnZero) || ($value === '0' && !$noValueOnZero)) {
            $row[$position] = $this->table->definition->columns[$position]->domain->kind === Kind::Double ? (float) $data->autoIncrement : $data->autoIncrement;
            $generated = $data->autoIncrement;
            $data->autoIncrement++;

            return [$row, $generated];
        }
        $number = (int) $value;
        if ($number >= $data->autoIncrement) {
            $data->autoIncrement = $number + 1;
        }

        return [$row, null];
    }

    /**
     * Answers the value a column takes when a row names none: its default, or none for a column without one.
     *
     * @return array{bool, int|float|string|null} Whether the column has a value, and the value
     */
    public function default(ColumnDefinition $column, Frame $frame): array
    {
        $default = $column->default;
        if (!$default->declared) {
            return [false, null];
        }
        if ($default->expression !== null) {
            $value = $default->expression->evaluate($frame);

            return [true, (new Store($this->context))->value($value, $default->expression->domain(), $column)];
        }

        return [true, $default->value];
    }

    /**
     * Answers the implicit default of a column's type: zero, the empty string, or the zero date.
     */
    public function implicit(ColumnDefinition $column): int|float|string
    {
        $domain = $column->domain;

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => 0,
            Kind::Double => 0.0,
            Kind::Decimal => $domain->decimals > 0 ? '0.' . str_repeat('0', $domain->decimals) : '0',
            Kind::Date => '0000-00-00',
            Kind::DateTime => '0000-00-00 00:00:00' . ($domain->decimals > 0 ? '.' . str_repeat('0', $domain->decimals) : ''),
            Kind::Time => '00:00:00' . ($domain->decimals > 0 ? '.' . str_repeat('0', $domain->decimals) : ''),
            Kind::Json => 'null',
            Kind::Bit => str_repeat("\0", (int) ceil($domain->length / 8)),
            default => $domain->field === Field::Enum ? ($domain->members[0] ?? '') : ($domain->field === Field::String && $domain->collation === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary() ? str_repeat("\0", $domain->length) : ''),
        };
    }
}
