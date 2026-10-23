<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

use DateTimeZone;

/**
 * A time zone: an offset from UTC, or a named zone of the time zone tables, and the conversion of times between it and UTC.
 *
 * A zone is named as `time_zone` and CONVERT_TZ name it: SYSTEM, the system time zone, which is
 * UTC here; an offset `+h:mm` or `-h:mm` from -13:59 to +14:00, written back as `+hh:mm`; or a
 * name installed in the instance, compared without regard to case or trailing spaces.
 * The default catalog uses PHP's IANA time-zone database. A caller may supply another
 * installation's names and identifiers independently of the MySQL release. The rules
 * of a named zone, with or without its `posix/` or `right/` prefix, follow PHP's tz
 * database, and a name PHP has no rules for is no
 * zone. The tables of the server hold transitions up to the end of 2037, so a later time keeps
 * the offset in force then; CET, MET, EET and WET follow Europe/Brussels, Europe/Athens and
 * Europe/Lisbon as the tables do. A local time a change of offset skips is the instant of the change; a local
 * time it repeats is the earlier instant (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/time-zone-support.html.
 *
 * @visibility MySqlMemory
 */
final class Zone
{
    /**
     * The last instant the time zone tables hold transitions for: 2037-12-31 23:59:59 UTC.
     */
    public const LAST_TRANSITION = 2145916799;

    /**
     * The names of the tables whose rules PHP reads under another name.
     */
    public const ALIASES = ['cet' => 'Europe/Brussels', 'met' => 'Europe/Brussels', 'eet' => 'Europe/Athens', 'wet' => 'Europe/Lisbon', 'posixrules' => 'America/New_York'];

    /**
     * @var list<array{string, int, bool}>|null The default installation from PHP’s IANA database
     */
    private static ?array $catalog = null;

    /**
     * @var array<string, string>|null The default names indexed without regard to case
     */
    private static ?array $names = null;

    /**
     * @var array<string, self> Named zones with their rules
     */
    private static array $zones = [];

    /**
     * @param string $name The name `time_zone` shows: SYSTEM, an offset `+hh:mm`, or the name in the tables
     * @param int $offset The offset from UTC in seconds of a zone of a fixed offset
     * @param DateTimeZone|null $rules The rules of a named zone, or null for a fixed offset
     */
    public function __construct(public readonly string $name, public readonly int $offset = 0, public readonly ?DateTimeZone $rules = null)
    {
    }

    /**
     * Answers an installed name or a fixed offset, independent of the MySQL release.
     *
     * @param array<string, string>|null $names Installed canonical names indexed in lowercase; null uses PHP's catalog
     */
    public static function named(string $text, ?array $names = null): ?self
    {
        return self::read($text, $names);
    }

    /**
     * Reads an installed name, SYSTEM or an offset; unknown names are SQL null.
     *
     * @param array<string, string>|null $names Installed canonical names indexed in lowercase
     */
    public static function read(string $text, ?array $names = null): ?self
    {
        if (preg_match('/\A([+-])([0-9]+):([0-9]+)\z/', $text, $match) === 1) {
            $hours = (int) ltrim($match[2], '0');
            $minutes = (int) ltrim($match[3], '0');
            $offset = ($hours * 60 + $minutes) * ($match[1] === '-' ? -1 : 1);
            if (strlen(ltrim($match[2], '0')) > 2 || strlen(ltrim($match[3], '0')) > 2 || $minutes > 59 || $offset > 840 || $offset < -839) {
                return null;
            }

            return new self(sprintf('%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv(abs($offset), 60), abs($offset) % 60), $offset * 60);
        }
        $trimmed = rtrim($text, ' ');
        if (strcasecmp($trimmed, 'SYSTEM') === 0) {
            return new self('SYSTEM');
        }
        $name = ($names ?? self::names())[strtolower($trimmed)] ?? null;
        if ($name === null) {
            return null;
        }
        if (isset(self::$zones[$name])) {
            return self::$zones[$name];
        }
        $base = (string) preg_replace('/\A(posix|right)\//', '', $name);
        set_error_handler(static fn (): bool => true);
        try {
            $rules = timezone_open(self::ALIASES[strtolower($base)] ?? $base);
        } finally {
            restore_error_handler();
        }
        if ($rules === false) {
            return null;
        }

        return self::$zones[$name] = new self($name, 0, $rules);
    }

    /**
     * Answers UTC.
     */
    public static function utc(): self
    {
        return new self('+00:00');
    }

    /**
     * Builds a default installation from the host's IANA database, with local sequential identifiers.
     *
     * @return list<array{string, int, bool}>
     */
    public static function catalog(): array
    {
        if (self::$catalog === null) {
            $names = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
            $names[] = 'posixrules';
            sort($names, SORT_STRING);
            self::$catalog = [];
            foreach ($names as $name) {
                self::$catalog[] = [$name, count(self::$catalog) + 1, false];
            }
        }
        return self::$catalog;
    }

    /**
     * Answers the default installation's names indexed without regard to case.
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        if (self::$names === null) {
            self::$names = [];
            foreach (self::catalog() as [$name]) {
                self::$names[strtolower($name)] = $name;
            }
        }
        return self::$names;
    }

    /**
     * Tells whether every time of the zone is a time of UTC.
     */
    public function universal(): bool
    {
        return $this->rules === null && $this->offset === 0;
    }

    /**
     * Answers the offset from UTC in seconds at an instant.
     *
     * @param int $instant Seconds since 1970-01-01 00:00:00 UTC
     */
    public function offsetAt(int $instant): int
    {
        if ($this->rules === null) {
            return $this->offset;
        }

        $moment = min($instant, self::LAST_TRANSITION);

        return $this->rules->getTransitions($moment, $moment)[0]['offset'] ?? 0;
    }

    /**
     * Answers the local time of an instant, as seconds of the local clock since 1970-01-01 00:00:00.
     */
    public function local(int $instant): int
    {
        return $instant + $this->offsetAt($instant);
    }

    /**
     * Answers the instant of a local time given as seconds of the local clock since 1970-01-01 00:00:00.
     */
    public function instant(int $local): int
    {
        if ($this->rules === null) {
            return $local - $this->offset;
        }
        if ($local > self::LAST_TRANSITION + 86400) {
            return $local - $this->offsetAt(self::LAST_TRANSITION);
        }
        $periods = $this->rules->getTransitions($local - 172800, $local + 172800);
        foreach ($periods as $index => $period) {
            $candidate = $local - $period['offset'];
            $end = $periods[$index + 1]['ts'] ?? PHP_INT_MAX;
            if (($index === 0 || $candidate >= $period['ts']) && $candidate < $end) {
                return $candidate;
            }
            if ($index > 0 && $candidate < $period['ts'] && $local - $periods[$index - 1]['offset'] >= $period['ts']) {
                return $period['ts'];
            }
        }

        return $local - $this->offsetAt($local);
    }
}
