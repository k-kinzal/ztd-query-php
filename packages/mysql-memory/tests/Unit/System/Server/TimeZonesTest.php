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

    public function testNamesAnswersTheZonesInTheOrderTheyAreNumbered(): void
    {
        self::assertSame(['Africa/Abidjan', 'posix/Africa/Abidjan', 'posixrules', 'right/Africa/Abidjan'], [TimeZones::names()[0], TimeZones::names()[598], TimeZones::names()[1196], TimeZones::names()[1197]]);
    }
}
