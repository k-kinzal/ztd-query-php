<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Temporal;
use MySqlMemory\Value\Zone;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Encodes temporal sort weights observed through WEIGHT_STRING on modern MySQL.
 * Date components precede hour, minute, second and fractional-second fields.
 * Signed time intervals reverse the full magnitude, including the fraction.
 * The sign bit is inverted so unsigned byte order follows temporal order.
 * Derived from native SQL observations, without server implementation code.
 *
 * @visibility MySqlMemory
 */
final class TemporalWeight
{
    /**
     * Answers the eight-byte weight of an already evaluated temporal value.
     */
    public function bytes(string $value, Kind $kind, ?Zone $zone = null): string
    {
        if ($kind === Kind::Time) {
            $parts = Temporal::parseTime($value);
            Check::invariant($parts !== null, 'An evaluated TIME has normalized temporal parts.');
            [$negative, $hour, $minute, $second, $micro] = $parts;
            $date = 0;
        } else {
            $parts = Temporal::parseDateTime($value);
            Check::invariant($kind->temporal() && $parts !== null, 'An evaluated date has normalized temporal parts.');
            [$year, $month, $day, $hour, $minute, $second, $micro] = $parts;
            if ($zone !== null && $year !== 0 && $month !== 0 && $day !== 0) {
                [$year, $month, $day, $hour, $minute, $second] = Calendar::moment($zone->instant(Calendar::epoch($year, $month, $day, $hour, $minute, $second)));
            }
            $date = (($year * 13 + $month) << 5) | $day;
            $negative = false;
        }
        $whole = ($date << 17) | ($hour << 12) | ($minute << 6) | $second;
        $magnitude = ($whole << 24) | $micro;

        return pack('J', ($negative ? -$magnitude : $magnitude) ^ PHP_INT_MIN);
    }

    /**
     * Answers a legacy column's sort bytes; cast counts do not change this width.
     */
    public function stored(string $value, Domain $domain, Zone $zone): string
    {
        $width = intdiv($domain->decimals + 1, 2);
        if ($domain->kind === Kind::Time) {
            $parts = Temporal::parseTime($value);
            Check::invariant($parts !== null, 'A stored TIME has normalized temporal parts.');
            [$negative, $hour, $minute, $second, $micro] = $parts;
            $whole = ($hour << 12) | ($minute << 6) | $second;
            $magnitude = ($whole << ($width * 8)) + intdiv($micro, 10 ** (6 - 2 * $width));
            $weight = ($negative ? -$magnitude : $magnitude) + (1 << (23 + $width * 8));
            return substr(pack('J', $weight), -3 - $width);
        }
        $parts = Temporal::parseDateTime($value);
        Check::invariant($parts !== null, 'A stored date has normalized temporal parts.');
        [$year, $month, $day, $hour, $minute, $second, $micro] = $parts;
        if ($domain->kind === Kind::Date) {
            return substr(pack('N', ($year << 9) | ($month << 5) | $day), 1);
        }
        $fraction = $width === 0 ? '' : substr(pack('N', intdiv($micro, 10 ** (6 - 2 * $width))), -$width);
        if ($domain->field === Field::Timestamp) {
            $seconds = $year === 0 || $month === 0 || $day === 0 ? 0 : $zone->instant(Calendar::epoch($year, $month, $day, $hour, $minute, $second));
            return pack('N', $seconds) . $fraction;
        }
        return substr($this->bytes($value, $domain->kind), 0, 5) . $fraction;
    }
}
