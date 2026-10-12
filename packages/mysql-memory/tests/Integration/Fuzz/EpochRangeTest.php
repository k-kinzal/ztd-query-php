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
final class EpochRangeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['+00:00', '+09:00', '-12:00'] as $zone) {
            foreach (['1970-01-01 00:00:00', '1970-01-01 00:00:01', '2038-01-19 03:14:07', '2038-01-19 03:14:08', '2099-01-01 00:00:00', '3001-01-18 23:59:59', '3001-01-19 00:00:00', '9999-12-31 23:59:59'] as $time) {
                yield $zone . $time => ["SET time_zone='" . $zone . "';SELECT UNIX_TIMESTAMP('" . $time . "'),CONVERT_TZ('" . $time . "','" . $zone . "','+01:00')"];
            }
            foreach (['-1', '0', '1', '2147483647', '2147483648', '32536771199', '32536771200'] as $epoch) {
                yield $zone . $epoch => ["SET time_zone='" . $zone . "';SELECT FROM_UNIXTIME(" . $epoch . ')'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testEpochRangeMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
