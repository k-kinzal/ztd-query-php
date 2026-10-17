<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

use DateTimeZone;
use SqlSemantics\Contract\GrammarRelease;

/**
 * A time zone: an offset from UTC, or a named zone of the time zone tables, and the conversion of times between it and UTC.
 *
 * A zone is named as `time_zone` and CONVERT_TZ name it: SYSTEM, the system time zone, which is
 * UTC here; an offset `+h:mm` or `-h:mm` from -13:59 to +14:00, written back as `+hh:mm`; or a
 * name of the time zone tables of the release, compared without regard to case or trailing
 * spaces. The names are those a server of the release loaded with mysql_tzinfo_to_sql holds, as
 * resources/time-zones holds them; a release without a catalog of its own has that of the latest
 * series of its major version. The rules of a named zone, with or without its `posix/` or
 * `right/` prefix, follow the tz database PHP carries, and a name PHP has no rules for is no
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
     * @var array<string, list<array{string, int, bool}>> The named zones of each release read so far, by the release
     */
    private static array $catalogs = [];

    /**
     * @var array<string, array<string, string>> The name of each named zone of each release, by the release and its lower-case name
     */
    private static array $names = [];

    /**
     * @var array<string, array<string, self>> The zones read so far, by the release and the text that named them
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
     * Answers the zone of a name on a release, or null when the name is no zone.
     */
    public static function named(string $text, GrammarRelease $release = GrammarRelease::MySql847): ?self
    {
        if (isset(self::$zones[$release->value][$text])) {
            return self::$zones[$release->value][$text];
        }
        $zone = self::read($text, $release);
        if ($zone !== null) {
            self::$zones[$release->value][$text] = $zone;
        }

        return $zone;
    }

    /**
     * Reads a zone from its name on a release, or answers null.
     */
    public static function read(string $text, GrammarRelease $release = GrammarRelease::MySql847): ?self
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
        $name = self::names($release)[strtolower($trimmed)] ?? null;
        if ($name === null) {
            return null;
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

        return new self($name, 0, $rules);
    }

    /**
     * Answers UTC.
     */
    public static function utc(): self
    {
        return new self('+00:00');
    }

    /**
     * Answers the named zones of the time zone tables of a release, in the order they are numbered: the name, its Time_zone_id and whether it counts leap seconds.
     *
     * @return list<array{string, int, bool}>
     */
    public static function catalog(GrammarRelease $release): array
    {
        if (!isset(self::$catalogs[$release->value])) {
            $directory = dirname(__DIR__, 2) . '/resources/time-zones/';
            $files = glob($directory . substr($release->value, 0, 9) . '*.php');
            $major = glob($directory . substr($release->value, 0, 7) . '*.php');
            $file = is_array($files) && $files !== [] ? $files[0] : (is_array($major) && $major !== [] ? $major[count($major) - 1] : $directory . GrammarRelease::MySql847->value . '.php');
            /** @var list<array{string, int, bool}> $zones */
            $zones = require $file;
            self::$catalogs[$release->value] = $zones;
        }

        return self::$catalogs[$release->value];
    }

    /**
     * Answers the names of the named zones of a release, by their lower-case names.
     *
     * @return array<string, string>
     */
    public static function names(GrammarRelease $release = GrammarRelease::MySql847): array
    {
        if (!isset(self::$names[$release->value])) {
            $names = [];
            foreach (self::catalog($release) as [$name]) {
                $names[strtolower($name)] = $name;
            }
            self::$names[$release->value] = $names;
        }

        return self::$names[$release->value];
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
