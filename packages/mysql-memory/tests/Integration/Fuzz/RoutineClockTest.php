<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class RoutineClockTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerZones(): iterable
    {
        foreach (['+00:00', '+09:00', '-03:30'] as $zone) {
            yield 'procedure ' . $zone => ['PROCEDURE', $zone];
            yield 'function ' . $zone => ['FUNCTION', $zone];
        }
    }

    #[DataProvider('providerZones')]
    public function testRoutineDatesUseStatementTimeAndTheReadingZone(string $kind, string $zone): void
    {
        [$target] = Servers::shared();
        $body = $kind === 'FUNCTION' ? 'RETURNS INT DETERMINISTIC RETURN 1' : 'SELECT 1';
        $result = $target->compare("SET timestamp=946684800, time_zone='+09:00'; CREATE {$kind} p() {$body}; SET timestamp=946771200; ALTER {$kind} p COMMENT 'changed'; SET time_zone='{$zone}'; SHOW {$kind} STATUS WHERE Db=DATABASE(); SELECT CREATED,LAST_ALTERED FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()");

        self::assertFalse($result->volatile, (string) $result->referenceDifference);
        self::assertNull($result->difference, (string) $result->difference);
    }
}
