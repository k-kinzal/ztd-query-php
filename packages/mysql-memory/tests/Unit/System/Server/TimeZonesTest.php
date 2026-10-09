<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\TimeZones;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeZones::class)]
#[Small]
final class TimeZonesTest extends TestCase
{
    public function testRowsNumbersTheZonesAsTheServerDoes(): void
    {
        $s = (new Instance())->connect();
        $names = $s->query("SELECT * FROM mysql.time_zone_name WHERE Name IN ('Africa/Abidjan', 'UTC', 'posixrules', 'right/UTC')")[0];
        $zones = $s->query('SELECT * FROM mysql.time_zone WHERE Time_zone_id IN (1, 1197, 1791)')[0];

        self::assertInstanceOf(ResultSet::class, $names);
        self::assertInstanceOf(ResultSet::class, $zones);
        self::assertSame([['Africa/Abidjan', '1'], ['posixrules', '1197'], ['right/UTC', '1791'], ['UTC', '594']], $names->rows);
        self::assertSame([['1', 'N'], ['1197', 'N'], ['1791', 'Y']], $zones->rows);
    }

    public function testRowsAreThoseOfTheTablesOfTheRelease(): void
    {
        $s = (new Instance('5.7.44'))->connect();
        $names = $s->query("SELECT * FROM mysql.time_zone_name WHERE Name IN ('Africa/Abidjan', 'America/Fort_Nelson', 'America/Fortaleza', 'UTC', 'posixrules', 'right/UTC')")[0];
        $count = $s->query('SELECT COUNT(*), SUM(Use_leap_seconds = \'Y\') FROM mysql.time_zone')[0];

        self::assertInstanceOf(ResultSet::class, $names);
        self::assertInstanceOf(ResultSet::class, $count);
        self::assertSame([['Africa/Abidjan', '1'], ['America/Fortaleza', '115'], ['America/Fort_Nelson', '113'], ['posixrules', '1193'], ['right/UTC', '1785'], ['UTC', '592']], $names->rows);
        self::assertSame([['1789', '596']], $count->rows);
    }

    public function testRowsListTheNamesInTheOrderTheyAreNumberedOnMySql56(): void
    {
        $s = (new Instance('5.6.51'))->connect();
        $names = $s->query('SELECT * FROM mysql.time_zone_name WHERE Time_zone_id BETWEEN 111 AND 114')[0];

        self::assertInstanceOf(ResultSet::class, $names);
        self::assertSame([['America/Ensenada', '111'], ['America/Fort_Nelson', '112'], ['America/Fort_Wayne', '113'], ['America/Fortaleza', '114']], $names->rows);
    }
}
