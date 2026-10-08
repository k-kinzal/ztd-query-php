<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use MySqlMemory\Value\Zone;
use Override;

/**
 * The rows of mysql.time_zone and mysql.time_zone_name: the named time zones the server knows, numbered as mysql_tzinfo_to_sql numbers them.
 *
 * The zones are those of the tz database PHP carries, each also under posix/ and, counting
 * leap seconds, under right/, and posixrules. They are numbered in the order of their names as
 * the zoneinfo directory lists them: the zones, then posix/, posixrules and right/ (verified on
 * a live 8.4.7 server, whose tables were loaded from the same tz database); mysql.time_zone_name
 * lists them by name, in its case-insensitive collation. The emulator keeps no transition
 * table: the rules of a zone come from PHP.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/time-zone-support.html.
 *
 * @visibility MySqlMemory
 */
final class TimeZones implements SystemRows
{
    /**
     * Answers a row for each zone: its number and whether it counts leap seconds, or its name and number.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $names = self::names();
        if ($reading->table->name === 'time_zone') {
            return array_map(static fn (string $name, int $position): array => ['Time_zone_id' => $position + 1, 'Use_leap_seconds' => str_starts_with($name, 'right/') ? 'Y' : 'N'], $names, array_keys($names));
        }
        $numbered = array_flip($names);
        uksort($numbered, static fn (string $left, string $right): int => strcmp(strtoupper($left), strtoupper($right)));

        return array_map(static fn (string $name, int $position): array => ['Name' => $name, 'Time_zone_id' => $position + 1], array_keys($numbered), $numbered);
    }

    /**
     * Answers the names of the zones in the order they are numbered.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $zones = array_values(array_diff(Zone::names(), ['posixrules']));
        sort($zones, SORT_STRING);

        return [...$zones, ...array_map(static fn (string $zone): string => 'posix/' . $zone, $zones), 'posixrules', ...array_map(static fn (string $zone): string => 'right/' . $zone, $zones)];
    }
}
