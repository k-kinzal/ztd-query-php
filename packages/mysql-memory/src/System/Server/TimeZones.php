<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use MySqlMemory\Value\Zone;
use Override;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The rows of mysql.time_zone and mysql.time_zone_name: the named time zones the server knows, numbered as mysql_tzinfo_to_sql numbers them.
 *
 * Names, identifiers and leap-second flags belong to the instance's installed data,
 * independently of the MySQL release. MySQL 5.6 lists MyISAM rows in installation
 * order; later releases order names using their case-insensitive table collation.
 * Transition rules are supplied by PHP's IANA database.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/time-zone-support.html.
 *
 * @visibility MySqlMemory
 */
final class TimeZones implements SystemRows
{
    /**
     * The default installation uses PHP's IANA database; no MySQL version implies a tzdata version.
     *
     * @return list<array{string, int, bool}>
     */
    public static function initial(): array
    {
        return Zone::catalog();
    }

    /**
     * Answers a row for each zone: its number and whether it counts leap seconds, or its name and number.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $zones = $reading->instance->timeZones;
        if ($reading->table->name === 'time_zone') {
            return array_map(static fn (array $zone): array => ['Time_zone_id' => $zone[1], 'Use_leap_seconds' => $zone[2] ? 'Y' : 'N'], $zones);
        }
        if ($reading->release !== GrammarRelease::MySql5651) {
            usort($zones, static fn (array $left, array $right): int => strcmp(strtoupper($left[0]), strtoupper($right[0])));
        }

        return array_map(static fn (array $zone): array => ['Name' => $zone[0], 'Time_zone_id' => $zone[1]], $zones);
    }
}
