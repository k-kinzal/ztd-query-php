<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Function\Time\Epochs;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Epochs::class)]
#[Small]
final class EpochsTest extends TestCase
{
    public function testRoutinesNamesTheFunctionsOfInstants(): void
    {
        self::assertSame(['UNIX_TIMESTAMP', 'FROM_UNIXTIME', 'CONVERT_TZ'], array_map(static fn ($routine): string => $routine->name, (new Epochs())->routines()));
    }

    public function testUnixReadsTheTimestampOfTheSessionAndDatesInItsZone(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET timestamp = 1700000000.25');
        $session->query("SET time_zone = 'Asia/Tokyo'");

        $reply = $session->query("SELECT UNIX_TIMESTAMP(), UNIX_TIMESTAMP('1970-01-01 09:00:00'), UNIX_TIMESTAMP('1970-01-01 09:00:01'), UNIX_TIMESTAMP('2024-01-01 00:00:00.500'), UNIX_TIMESTAMP('3001-01-19 09:00:00')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[1700000000, 0, 1, '1704034800.500', 0]], $reply->rows);
    }

    public function testFromUnixWritesAnInstantInTheZoneOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET time_zone = 'Europe/Paris'");

        $reply = $session->query("SELECT FROM_UNIXTIME(1711846800), FROM_UNIXTIME(-1), FROM_UNIXTIME(1.5), FROM_UNIXTIME(1700000000, '%Y'), FROM_UNIXTIME(1.9999999)")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-03-31 03:00:00', null, '1970-01-01 01:00:01.5', '2023', '1970-01-01 01:00:02.000000']], $reply->rows);
    }

    public function testConvertMovesBetweenZonesWithinTheRangeOfInstants(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT CONVERT_TZ('2024-03-31 02:30:00', 'Europe/Paris', 'UTC'), CONVERT_TZ('2024-10-27 02:30:00', 'Europe/Paris', 'UTC'), CONVERT_TZ('2050-07-01 00:00:00', 'UTC', 'Australia/Sydney'), CONVERT_TZ('1970-01-01 00:00:00', '+00:00', '+01:00'), CONVERT_TZ('2024-01-01', 'x', 'UTC')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-03-31 01:00:00', '2024-10-27 00:30:00', '2050-07-01 11:00:00', '1970-01-01 00:00:00', null]], $reply->rows);
    }
}
