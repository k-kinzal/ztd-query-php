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
final class EventScheduleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSchedules(): iterable
    {
        foreach (['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'] as $unit) {
            yield $unit . ' computed interval' => ['ALTER EVENT missing ON SCHEDULE EVERY USER() ' . $unit];
        }
        foreach (["'x'", "'2x'", "CONCAT('2','x')", '1.5', "'1.5'", "'1e2'", '-1', '0', 'NULL'] as $value) {
            foreach (['SECOND', 'DAY'] as $unit) {
                yield $unit . ' ' . $value => ['ALTER EVENT missing ON SCHEDULE EVERY ' . $value . ' ' . $unit];
            }
        }
        yield 'session clock and zone' => ["SET timestamp=1700000000; SET time_zone='+09:00'; CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1; SHOW CREATE EVENT e"];
        yield 'one time session clock' => ["SET timestamp=1700000000; CREATE EVENT e ON SCHEDULE AT '2024-01-01' DO SELECT 1; SHOW CREATE EVENT e"];
        yield 'end after session clock' => ["SET timestamp=1700000000; CREATE EVENT e ON SCHEDULE EVERY 1 DAY ENDS '2024-01-01' DO SELECT 1; SHOW CREATE EVENT e"];
        yield 'decimal rounds' => ['CREATE EVENT e ON SCHEDULE EVERY 1.5 DAY DO SELECT 1; SHOW CREATE EVENT e'];
        yield 'text truncates' => ["CREATE EVENT e ON SCHEDULE EVERY '1.5' DAY DO SELECT 1; SHOW CREATE EVENT e"];
        yield 'scientific text truncates' => ["CREATE EVENT e ON SCHEDULE EVERY '1e2' DAY DO SELECT 1; SHOW CREATE EVENT e"];
    }

    #[DataProvider('providerSchedules')]
    public function testScheduleMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);
        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
