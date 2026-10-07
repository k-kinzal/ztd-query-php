<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Result\FieldType;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Kind;

/**
 * Stores values into ENUM and SET columns: a member, or a set of members in declared order.
 *
 * A member is found by its name in the collation of the column, or by its position for a
 * number. A value that names no member is refused under a strict mode and stored as the empty
 * string otherwise (WARN_DATA_TRUNCATED).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/enum.html.
 *
 * @visibility MySqlMemory
 */
final class Members
{
    /**
     * @param Store $store The store writing the row
     */
    public function __construct(public readonly Store $store)
    {
    }

    /**
     * Stores a text into an ENUM or SET column.
     */
    public function value(string $text, Domain $from, ColumnDefinition $column): string
    {
        $members = $column->domain->members;
        if ($column->domain->field === FieldType::Enum) {
            $member = $this->member($text, $from, $members, $column);
            if ($member === null) {
                $this->store->adjust(ErrorCode::DataTruncated, $column->name, $this->store->row);

                return '';
            }

            return $member;
        }
        $chosen = [];
        foreach ($text === '' ? [] : explode(',', $text) as $part) {
            $member = $this->member($part, $from, $members, $column);
            if ($member === null) {
                $this->store->adjust(ErrorCode::DataTruncated, $column->name, $this->store->row);

                continue;
            }
            $chosen[$member] = true;
        }

        return implode(',', array_values(array_filter($members, static fn (string $member): bool => isset($chosen[$member]))));
    }

    /**
     * Finds the member a text or a position names.
     *
     * @param list<string> $members
     */
    public function member(string $text, Domain $from, array $members, ColumnDefinition $column): ?string
    {
        if ($from->kind->numeric() && $from->kind !== Kind::Decimal && preg_match('/\A[0-9]+\z/', $text) === 1) {
            $position = (int) $text;

            return $position === 0 ? '' : ($members[$position - 1] ?? null);
        }
        foreach ($members as $member) {
            if ($column->domain->collation->compare(rtrim($member, ' '), rtrim($text, ' ')) === 0) {
                return $member;
            }
        }

        return null;
    }
}
