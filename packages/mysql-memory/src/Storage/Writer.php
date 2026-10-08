<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use Closure;
use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Call\Clock as ClockKind;
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
     * The row is checked against the latest rows, and against the committed versions of the rows
     * other open transactions changed or deleted. A conflicting row another transaction holds is
     * waited for, as InnoDB waits to lock a duplicate, and the check starts again once it is
     * locked; the duplicate is then locked shared, or exclusively when the statement goes on to
     * change it.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html.
     *
     * @param list<int|float|string|null> $row
     * @param LockMode $mode The lock the statement takes of the duplicate
     * @return array{int, Key}|null
     *
     * @throws SqlError When the duplicate cannot be locked
     */
    public function conflict(array $row, ?int $except = null, LockMode $mode = LockMode::Shared): ?array
    {
        $variables = $this->context->variables;
        $transaction = $variables->instance->transactions->of($variables->connection);
        for (;;) {
            $found = $this->existing($row, $except, $transaction);
            if ($found === null || $transaction === null) {
                return $found;
            }
            $waited = $transaction->access->contended($this->table, $found[0], $mode);
            $transaction->access->lock($this->table, $found[0], $mode);
            if (!$waited) {
                return $found;
            }
        }
    }

    /**
     * Answers the number of the first row a row duplicates in a unique key among the rows a write checks, and the key; null when none.
     *
     * @param list<int|float|string|null> $row
     * @return array{int, Key}|null
     */
    public function existing(array $row, ?int $except, ?\MySqlMemory\Session\Transaction $transaction): ?array
    {
        $definition = $this->table->definition;
        $rows = $transaction?->access->rows($this->table, LockMode::Shared) ?? $this->table->data->rows;
        $earlier = $transaction === null ? [] : $transaction->system->earlier($this->table, $transaction);
        foreach ($definition->keys as $key) {
            if (!$key->unique()) {
                continue;
            }
            $wanted = $this->key($row, $key);
            if ($wanted === null) {
                continue;
            }
            foreach ([$rows, $earlier] as $candidates) {
                foreach ($candidates as $number => $existing) {
                    if ($existing !== null && $number !== $except && $this->key($existing, $key) === $wanted) {
                        return [$number, $key];
                    }
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
                $value = Encoding::slice((string) $value, 0, $prefix, $domain->collation->charset);
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
        return DataError::DuplicateEntry->error(...$this->entry($row, $key));
    }

    /**
     * Warns of a row IGNORE leaves out because it duplicates a unique key; MySQL 5.6 leaves it out silently (verified on a live 5.6.51 server).
     *
     * @param list<int|float|string|null> $row
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public function ignored(array $row, Key $key): void
    {
        if ($this->context->modes->release !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $this->context->warning(DataError::DuplicateEntry, ...$this->entry($row, $key));
        }
    }

    /**
     * Answers the entry and key name ER_DUP_ENTRY names for a conflict.
     *
     * The key is named after its table from 8.0 on, alone in 5.6 and 5.7 (verified on live 5.6.51
     * and 5.7.44 servers).
     *
     * @param list<int|float|string|null> $row
     * @return array{string, string}
     */
    public function entry(array $row, Key $key): array
    {
        $values = [];
        foreach ($key->columns as $position) {
            $domain = $this->table->definition->columns[$position]->domain;
            $values[] = Convert::shown((string) Convert::toText($row[$position], $domain), $domain->kind === Kind::String && $domain->collation->charset !== \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::binary() ? $domain->collation->charset : null);
        }

        $legacy = in_array($this->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);

        return [mb_strcut(implode('-', $values), 0, 64, 'UTF-8'), ($legacy ? '' : $this->table->definition->name . '.') . $key->name];
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

            return [true, (new Store($this->context, 1, $this->table->definition->name))->value($value, $default->expression->domain(), $column)];
        }

        return [true, $default->value];
    }

    /**
     * Sets the columns declared ON UPDATE CURRENT_TIMESTAMP that no assignment wrote to the time of the statement, in a row an update changes.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
     *
     * @param list<int|float|string|null> $row The changed row
     * @param array<int, true> $assigned The positions of the columns the assignments wrote
     * @return list<int|float|string|null>
     *
     * @throws SqlError When the time cannot be stored
     */
    public function refresh(array $row, array $assigned): array
    {
        foreach ($this->table->definition->columns as $position => $column) {
            if ($column->onUpdateNow && !isset($assigned[$position])) {
                $clock = new Clock(ClockKind::Now, $column->domain);
                array_splice($row, $position, 1, [(new Store($this->context, 1, $this->table->definition->name))->value($clock->evaluate(new Frame($this->context)), $clock->domain(), $column)]);
            }
        }

        return $row;
    }

    /**
     * Computes the generated columns of a row in column order, each from the values before it, stored as the column stores a value.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html.
     *
     * @param list<int|float|string|null> $row
     * @param Closure(int, int|float|string|null): (int|float|string|null)|null $notNull Decides what NULL in a NOT NULL generated column becomes, by its position
     * @return list<int|float|string|null>
     *
     * @throws SqlError When a value cannot be stored
     */
    public function generate(array $row, Store $store, ?Closure $notNull = null): array
    {
        $frame = new Frame($this->context);
        foreach ($this->table->definition->columns as $position => $column) {
            if ($column->generated === null) {
                continue;
            }
            $frame->row = $row;
            $value = $store->value($column->generated->evaluate($frame), $column->generated->domain(), $column);
            array_splice($row, $position, 1, [$value === null && !$column->nullable() && $notNull !== null ? $notNull($position, $value) : $value]);
        }

        return $row;
    }

    /**
     * Answers the first enforced CHECK constraint of the table a row violates, in the order of their names, or null: a condition that is false; NULL passes.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
     *
     * @param list<int|float|string|null> $row
     */
    public function violated(array $row): ?\MySqlMemory\Dictionary\Check
    {
        $frame = new Frame($this->context, $row);
        foreach ($this->table->definition->checks as $check) {
            if ($check->enforced && Convert::toBool($check->condition->evaluate($frame), $check->condition->domain(), $this->context) === false) {
                return $check;
            }
        }

        return null;
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
            Kind::String, Kind::Null => $domain->field === Field::Enum ? ($domain->members[0] ?? '') : ($domain->field === Field::String && $domain->collation === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary() ? str_repeat("\0", $domain->length) : ''),
        };
    }
}
