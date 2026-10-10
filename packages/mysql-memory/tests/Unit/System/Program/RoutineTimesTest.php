<?php

declare(strict_types=1);

namespace Tests\Unit\System\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Program\RoutineTimes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutineTimes::class)]
#[Small]
final class RoutineTimesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerZones(): iterable
    {
        yield 'UTC' => ['+00:00', '2000-01-01 00:00:00'];
        yield 'east' => ['+09:00', '2000-01-01 09:00:00'];
        yield 'west' => ['-03:30', '1999-12-31 20:30:00'];
    }

    #[DataProvider('providerZones')]
    public function testLocalDisplaysInstalledInstantsInTheReadingSession(string $zone, string $created): void
    {
        $session = (new Instance(routineTimestamps: ['FUNCTION:version_major' => [946684800, 946684800]]))->connect();
        $session->query("SET time_zone='{$zone}'");
        $result = $session->query("SELECT CREATED,LAST_ALTERED FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='sys' AND ROUTINE_NAME='version_major'")[0];
        $listing = $session->query("SHOW FUNCTION STATUS WHERE Db='sys' AND Name='version_major'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $listing);
        self::assertSame([[$created, $created]], $result->rows);
        self::assertSame([$created, $created], array_slice($listing->rows[0], 5, 2));
    }

    public function testLocalKeepsCreationAndAlterationInstantsIndependentOfTheCreationZone(): void
    {
        $session = (new Instance(databases: ['d']))->connect();
        $session->query("SET time_zone='+09:00', timestamp=946684800; CREATE PROCEDURE d.p() SELECT 1; SET timestamp=946771200; ALTER PROCEDURE d.p COMMENT 'changed'; SET time_zone='+00:00'");
        $result = $session->query("SELECT CREATED,LAST_ALTERED FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='d'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2000-01-01 00:00:00', '2000-01-02 00:00:00']], $result->rows);
    }
}
