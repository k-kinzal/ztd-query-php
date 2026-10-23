<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * The kind of a leg of a JSON path: a member, every member, a cell, every cell, a range of cells, or every value below.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Searching and Modifying JSON Values").
 *
 * @visibility MySqlMemory
 */
enum JsonLegKind
{
    case Member;
    case AnyMember;
    case Cell;
    case AnyCell;
    case Range;
    case Descendants;

    /**
     * Tells whether a leg of the kind can select more than one value, so that a path with it selects an array of values.
     *
     * @example A range of cells
     *     \MySqlMemory\Value\Json\JsonLegKind::Range->wild() // => true
     */
    public function wild(): bool
    {
        return match ($this) {
            self::AnyMember, self::AnyCell, self::Range, self::Descendants => true,
            self::Member, self::Cell => false,
        };
    }
}
