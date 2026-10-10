<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Statement\Snapshot;

/**
 * A locale lc_time_names takes: the names of the months and days DATE_FORMAT, DAYNAME and MONTHNAME write.
 *
 * The locales are numbered in the order the server lists them; SET lc_time_names takes a name,
 * compared without regard to case, or a number. The list was read from a server of each
 * supported release and is the same in all of them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/locale-support.html.
 *
 * @visibility public
 * @example Reading the name of February in German
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale::named('de_de')?->months[1] // => 'Februar'
 */
final class Locale
{
    use Snapshot;

    /**
     * @var list<self>|null
     */
    private static ?array $all = null;

    /**
     * @param string $name The name, such as en_US
     * @param list<string> $months The names of the months, from January
     * @param list<string> $shortMonths The abbreviated names of the months, from January
     * @param list<string> $days The names of the days, from Monday
     * @param list<string> $shortDays The abbreviated names of the days, from Monday
     */
    public function __construct(public readonly string $name, public readonly array $months, public readonly array $shortMonths, public readonly array $days, public readonly array $shortDays)
    {
    }

    /**
     * Answers every locale, in the order of their numbers.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        if (self::$all === null) {
            /** @var list<array{string, list<string>, list<string>, list<string>, list<string>}> $rows */
            $rows = require dirname(__DIR__, 4) . '/resources/locales.php';
            self::$all = array_map(static fn (array $row): self => new self($row[0], $row[1], $row[2], $row[3], $row[4]), $rows);
        }

        return self::$all;
    }

    /**
     * Finds a locale by name, without regard to case, or answers null.
     */
    public static function named(string $name): ?self
    {
        foreach (self::all() as $locale) {
            if (strcasecmp($locale->name, $name) === 0) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Finds a locale by its number, or answers null.
     */
    public static function numbered(int $number): ?self
    {
        return self::all()[$number] ?? null;
    }

    /**
     * Answers en_US, the locale of a new session.
     */
    public static function default(): self
    {
        return self::all()[0];
    }

    /**
     * Answers the length in characters of the longest month name: the length of MONTHNAME.
     */
    public function longestMonth(): int
    {
        return array_reduce($this->months, static fn (int $longest, string $name): int => max($longest, (int) preg_match_all('/./su', $name)), 0);
    }

    /**
     * Answers the length in characters of the longest day name: the length of DAYNAME.
     */
    public function longestDay(): int
    {
        return array_reduce($this->days, static fn (int $longest, string $name): int => max($longest, (int) preg_match_all('/./su', $name)), 0);
    }
}
