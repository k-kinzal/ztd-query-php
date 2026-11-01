<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Storage\TimestampZones;
use MySqlMemory\Value\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimestampZones::class)]
#[Small]
final class TimestampZonesTest extends TestCase
{
    public function testLocalShowsATimestampInTheZoneOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (ts TIMESTAMP(2) NULL, dt DATETIME NULL)');
        $session->query("INSERT INTO t VALUES ('2024-01-01 00:00:00.5', '2024-01-01 00:00:00')");
        $session->query("SET time_zone = '+09:00'");

        $reply = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-01-01 09:00:00.50', '2024-01-01 00:00:00']], $reply->rows);
    }

    public function testRowsKeepsTheRowsOfATableWithoutATimestamp(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1)');
        $session->query("SET time_zone = '+09:00'");

        $reply = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['1']], $reply->rows);
    }

    public function testStampedTellsATableWithATimestamp(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (ts TIMESTAMP NULL)');
        $table = $session->instance->dictionary->schema('d')?->table('t');

        self::assertNotNull($table);
        self::assertTrue((new TimestampZones())->stamped($table));
    }

    public function testShownWritesUtcAsTheLocalTime(): void
    {
        self::assertSame(['2024-01-01 09:00:00.500', '0000-00-00 00:00:00'], [(new TimestampZones())->shown('2024-01-01 00:00:00.5', 3, Zone::named('Asia/Tokyo') ?? Zone::utc()), (new TimestampZones())->shown('0000-00-00 00:00:00', 0, Zone::named('Asia/Tokyo') ?? Zone::utc())]);
    }

    public function testUniversalWritesALocalTimeAsUtc(): void
    {
        self::assertSame('2023-12-31 23:00:00', (new TimestampZones())->universal('2024-01-01 00:00:00', 0, Zone::named('Europe/Paris') ?? Zone::utc()));
    }

    public function testSkippedTellsATimeAChangeOfOffsetSkips(): void
    {
        self::assertSame([true, false], [(new TimestampZones())->skipped('2024-03-31 02:30:00', Zone::named('Europe/Paris') ?? Zone::utc()), (new TimestampZones())->skipped('2024-10-27 02:30:00', Zone::named('Europe/Paris') ?? Zone::utc())]);
    }

    public function testMovedKeepsTheFraction(): void
    {
        self::assertSame('1970-01-01 00:00:10.25', (new TimestampZones())->moved('1970-01-01 00:00:00.25', 2, static fn (int $seconds): int => $seconds + 10));
    }

    public function testLocalAfterAnUpdateUnderAnotherZone(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (ts TIMESTAMP NULL, v INT)');
        $session->query("SET time_zone = 'Europe/Paris'");
        $session->query("INSERT INTO t VALUES ('2024-07-01 12:00:00', 1)");
        $session->query("UPDATE t SET v = 2 WHERE ts = '2024-07-01 12:00:00'");
        $session->query("SET time_zone = 'UTC'");

        $reply = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-07-01 10:00:00', '2']], $reply->rows);
    }
}
