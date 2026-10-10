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
final class BinaryLogBaselineTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerListings(): iterable
    {
        yield 'files' => ['SHOW BINARY LOGS'];
        yield 'events' => ['SHOW BINLOG EVENTS'];
        yield 'floating position' => ['SHOW BINLOG EVENTS FROM 1e2'];
        yield 'fractional exponent' => ['SHOW BINLOG EVENTS FROM 1.27e2'];
        yield 'boundary exponent' => ['SHOW BINLOG EVENTS FROM 127e0'];
        yield 'leading fraction' => ['SHOW BINLOG EVENTS FROM .1'];
        yield 'overflow' => ['SHOW BINLOG EVENTS FROM 18446744073709551616'];
        yield 'rotation within input' => ['FLUSH BINARY LOGS; SHOW BINARY LOGS; SHOW BINLOG EVENTS'];
    }

    #[DataProvider('providerListings')]
    public function testIsolatedLogListingsMatchWithoutNormalizingTheirContents(string $sql): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $comparison = $target->compare($sql);
        $server->stop();

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    public function testLogPositionStartsFromTheSameStateAcrossObservations(): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $sql = version_compare($target->version, '8.4.0', '>=') ? 'SHOW BINARY LOG STATUS' : 'SHOW MASTER STATUS';
        $rotated = $target->compare('FLUSH BINARY LOGS; ' . $sql);
        $reset = $target->compare($sql);
        $server->stop();

        self::assertFalse($rotated->volatile, (string) $rotated->referenceDifference);
        self::assertNull($rotated->difference, (string) $rotated->difference);
        self::assertFalse($reset->volatile, (string) $reset->referenceDifference);
        self::assertNull($reset->difference, (string) $reset->difference);
    }

    public function testComparisonStillDetectsTheUnimplementedLoggingOfWrites(): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $comparison = $target->compare('INSERT INTO t1(id) VALUES (6); SHOW BINARY LOGS');
        $server->stop();

        self::assertNotNull($target->baseline);
        $logging = ($target->baseline->globals['log_bin'] ?? 'OFF') === 'ON';
        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertSame($logging, $comparison->difference !== null, (string) $comparison->difference);
    }
}
