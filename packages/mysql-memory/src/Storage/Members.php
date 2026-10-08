<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Stores values into ENUM and SET columns: a member, or a set of members in declared order.
 *
 * A member is found by its name in the collation of the column. A number names an ENUM member
 * by its position (from 1), and a SET by its bits: bit 0 is the first member. A number keeps its
 * integer part. A text that names no member is read as such a number when it is one: digits
 * after optional spaces and a plus sign (ENUM admits trailing spaces too); the text '0' is the
 * empty string. A value that names no member, a number out of range, and the bits of a number
 * beyond the members are refused under a strict mode and dropped otherwise (WARN_DATA_TRUNCATED);
 * a number 0 is a refused ENUM value but the empty SET.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/enum.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set.html.
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
     *
     * @throws SqlError When the value is refused
     */
    public function value(string $text, Domain $from, ColumnDefinition $column): string
    {
        $members = $column->domain->members;
        $numeric = $from->kind->numeric() && $from->kind !== Kind::Bit;
        if ($column->domain->field === Field::Enum) {
            $member = $numeric ? null : $this->member($text, $members, $column);
            if ($member !== null) {
                return $member;
            }
            $number = $numeric ? $this->integer($text, $from) : $this->digits($text, true);
            if ($number !== null && ($number === '0' && !$numeric || bccomp($number, '1', 0) >= 0 && bccomp($number, (string) count($members), 0) <= 0)) {
                return $number === '0' ? '' : $members[(int) $number - 1];
            }
            $this->store->adjust(DataError::DataTruncated, $column->name, $this->store->row);

            return '';
        }
        if ($numeric) {
            return $this->bits($this->integer($text, $from), $members, $column, true);
        }
        $chosen = [];
        $missing = false;
        foreach ($text === '' ? [] : explode(',', $text) as $part) {
            $member = $this->member($part, $members, $column);
            if ($member === null) {
                $missing = true;

                continue;
            }
            $chosen[$member] = true;
        }
        $number = $missing ? $this->digits($text, false) : null;
        if ($number !== null) {
            return $this->bits($number, $members, $column, false);
        }
        if ($missing) {
            $this->store->adjust(DataError::DataTruncated, $column->name, $this->store->row);
        }

        return implode(',', array_values(array_filter($members, static fn (string $member): bool => isset($chosen[$member]))));
    }

    /**
     * Finds the member a text names, comparing without trailing spaces in the collation of the column.
     *
     * @param list<string> $members
     */
    public function member(string $text, array $members, ColumnDefinition $column): ?string
    {
        foreach ($members as $member) {
            if (Ordering::of($column->domain->collation)->compare(rtrim($member, ' '), rtrim($text, ' ')) === 0) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Answers the integer part of a number, as decimal digits with an optional minus sign.
     *
     * @return numeric-string
     */
    public function integer(string $text, Domain $from): string
    {
        if ($from->kind === Kind::Double) {
            $value = (float) $text;
            $digits = sprintf('%.0f', $value < 0 ? -floor(-$value) : floor($value));

            return $digits === '-0' ? '0' : $digits;
        }
        $number = preg_match('/\A(-?)0*([0-9]+)/', $text, $match) === 1 && $match[2] !== '0' ? $match[1] . $match[2] : '0';

        return is_numeric($number) ? $number : '0';
    }

    /**
     * Answers the number a text writes as digits, or null when it is not one.
     *
     * @param bool $trailing Whether spaces may follow the digits
     * @return numeric-string|null
     */
    public function digits(string $text, bool $trailing): ?string
    {
        if (preg_match($trailing ? '/\A *\+?([0-9]+) *\z/' : '/\A *\+?([0-9]+)\z/', $text, $match) !== 1) {
            return null;
        }
        $digits = ltrim($match[1], '0');

        return is_numeric($digits) ? $digits : '0';
    }

    /**
     * Answers the members the bits of a number name, dropping the bits beyond the members.
     *
     * A negative number is read as its 64-bit two's complement. When bits are dropped, a number
     * the statement computed keeps the bits of the members and a text keeps none.
     *
     * @param numeric-string $number The number
     * @param list<string> $members
     * @param bool $computed Whether the number is a numeric value rather than a text
     *
     * @throws SqlError When bits are dropped under a strict mode
     */
    public function bits(string $number, array $members, ColumnDefinition $column, bool $computed): string
    {
        if (str_starts_with($number, '-')) {
            $number = bcadd(bcpow('2', '64', 0), $number, 0);
        }
        $limit = bcpow('2', (string) count($members), 0);
        $kept = bcmod($number, $limit, 0);
        if (bccomp($kept, $number, 0) !== 0) {
            $this->store->adjust(DataError::DataTruncated, $column->name, $this->store->row);
            if (!$computed) {
                return '';
            }
        }
        $chosen = [];
        foreach ($members as $position => $member) {
            if (bcmod(bcdiv($kept, bcpow('2', (string) $position, 0), 0), '2', 0) === '1') {
                $chosen[] = $member;
            }
        }

        return implode(',', $chosen);
    }
}
