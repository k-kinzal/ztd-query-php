<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use Closure;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;
use MySqlMemory\Value\Zone;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Converts the values of TIMESTAMP columns between UTC, which the tables hold, and the time zone of the session, which statements read and write.
 *
 * A value is stored in UTC and shown in the time zone of the session, so it shows another time
 * when time_zone changes; the zero value is the same in every zone.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/datetime.html.
 *
 * @visibility MySqlMemory
 */
final class TimestampZones
{
    /**
     * Answers a row of a table as the session reads it: each TIMESTAMP in the time zone of the session.
     *
     * @param list<int|float|string|null> $row The stored row
     * @return list<int|float|string|null>
     */
    public function local(StoredTable $table, array $row, Context $context): array
    {
        $zone = $context->zone();
        if ($zone->universal()) {
            return $row;
        }
        foreach ($table->definition->columns as $position => $column) {
            $value = $row[$position] ?? null;
            if ($column->domain->field === Field::Timestamp && is_string($value)) {
                $row[$position] = $this->shown($value, $column->domain->decimals, $zone);
            }
        }

        return $row;
    }

    /**
     * Answers the rows of a table as the session reads them, by row number.
     *
     * @param array<int, list<int|float|string|null>> $rows
     * @return array<int, list<int|float|string|null>>
     */
    public function rows(StoredTable $table, array $rows, Context $context): array
    {
        if ($context->zone()->universal() || !$this->stamped($table)) {
            return $rows;
        }

        return array_map(fn (array $row): array => $this->local($table, $row, $context), $rows);
    }

    /**
     * Tells whether a table has a TIMESTAMP column.
     */
    public function stamped(StoredTable $table): bool
    {
        foreach ($table->definition->columns as $column) {
            if ($column->domain->field === Field::Timestamp) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes a datetime in UTC as the local time of a zone; the zero value stays as it is.
     */
    public function shown(string $utc, int $decimals, Zone $zone): string
    {
        return $this->moved($utc, $decimals, static fn (int $seconds): int => $zone->local($seconds));
    }

    /**
     * Writes a local datetime of a zone as UTC; the zero value stays as it is.
     */
    public function universal(string $local, int $decimals, Zone $zone): string
    {
        return $this->moved($local, $decimals, static fn (int $seconds): int => $zone->instant($seconds));
    }

    /**
     * Tells whether a local datetime of a zone is a time a change of offset skips.
     */
    public function skipped(string $local, Zone $zone): bool
    {
        $parts = Temporal::parseDateTime($local);
        if ($parts === null || $zone->rules === null || $parts[1] === 0 || $parts[2] === 0) {
            return false;
        }
        $seconds = Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]);

        return $zone->local($zone->instant($seconds)) !== $seconds;
    }

    /**
     * Moves the seconds of a datetime by a conversion, keeping its fraction.
     *
     * @param Closure(int): int $conversion
     */
    public function moved(string $text, int $decimals, Closure $conversion): string
    {
        $parts = Temporal::parseDateTime($text);
        if ($parts === null || ($parts[0] === 0 && $parts[1] === 0 && $parts[2] === 0) || $parts[1] === 0 || $parts[2] === 0) {
            return $text;
        }
        $moment = Calendar::moment($conversion(Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5])));

        return Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $parts[6], $decimals);
    }
}
