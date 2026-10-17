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
final class EventScheduleBoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['+00:00', '+09:00', '-12:00'] as $zone) {
            foreach (['1970-01-01 00:00:00', '1970-01-01 00:00:01', '2038-01-19 03:14:07', '2038-01-19 03:14:08', '2099-01-01 00:00:00', '3001-01-18 23:59:59', '3001-01-19 00:00:00', '9999-12-31 23:59:59'] as $time) {
                foreach (['AT', 'STARTS', 'ENDS'] as $clause) {
                    $schedule = $clause === 'AT' ? 'AT' : 'EVERY 1 YEAR ' . $clause;
                    yield $zone . $clause . $time => ["SET time_zone='" . $zone . "';SET timestamp=1600000000;CREATE EVENT e ON SCHEDULE " . $schedule . " '" . $time . "' ON COMPLETION PRESERVE DISABLE DO SELECT 1"];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPastSchedules(): iterable
    {
        foreach (['', 'ON COMPLETION PRESERVE'] as $completion) {
            foreach (['', 'ENABLE', 'DISABLE', 'DISABLE ON SLAVE'] as $status) {
                foreach ([false, true] as $alter) {
                    $sql = $alter
                        ? "CREATE EVENT e ON SCHEDULE AT '2030-01-01' " . $completion . ' ' . $status . " DO SELECT 1;ALTER EVENT e ON SCHEDULE AT '1970-01-02'"
                        : "CREATE EVENT e ON SCHEDULE AT '1970-01-02' " . $completion . ' ' . $status . ' DO SELECT 1';
                    yield $completion . $status . ($alter ? ' alter' : ' create') => ['SET timestamp=1600000000;' . $sql];
                }
            }
        }
        $future = "SET timestamp=1600000000;CREATE EVENT e ON SCHEDULE AT '2030-01-01' ";
        yield 'missing event' => ["SET timestamp=1600000000;ALTER EVENT missing ON SCHEDULE AT '1970-01-02'"];
        yield 'alter explicitly disabled' => [$future . "ON COMPLETION PRESERVE DO SELECT 1;ALTER EVENT e ON SCHEDULE AT '1970-01-02' DISABLE"];
        yield 'alter replica disabled' => [$future . "ON COMPLETION PRESERVE DO SELECT 1;ALTER EVENT e ON SCHEDULE AT '1970-01-02' DISABLE ON SLAVE"];
        yield 'unpreserved altered disabled' => [$future . "DO SELECT 1;ALTER EVENT e ON SCHEDULE AT '1970-01-02' DISABLE"];
        yield 'missing rename database' => [$future . "DO SELECT 1;ALTER EVENT e ON SCHEDULE AT '1970-01-02' RENAME TO nodb.e"];
        yield 'same rename' => [$future . "DO SELECT 1;ALTER EVENT e ON SCHEDULE AT '1970-01-02' RENAME TO e"];
        $past = "SET timestamp=1600000000;CREATE EVENT e ON SCHEDULE AT '1970-01-02' ON COMPLETION PRESERVE DISABLE DO SELECT 1;";
        yield 'completion only' => [$past . 'ALTER EVENT e ON COMPLETION NOT PRESERVE'];
        yield 'comment only' => [$past . "ALTER EVENT e COMMENT 'updated'"];
    }

    #[DataProvider('providerStatements')]
    #[DataProvider('providerPastSchedules')]
    public function testEventScheduleBoundsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
