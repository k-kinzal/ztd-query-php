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
final class MutexBaselineTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'fixture setting' => ['SELECT @@GLOBAL.innodb_monitor_reset'];
        yield 'default is null' => ['SET GLOBAL innodb_monitor_reset=DEFAULT; SELECT @@GLOBAL.innodb_monitor_reset'];
        yield 'explicit null is rejected' => ['SET GLOBAL innodb_monitor_reset=NULL'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'input changes remain visible' => ["SET GLOBAL innodb_monitor_reset='all'; SELECT @@GLOBAL.innodb_monitor_reset"];
            yield 'all engines' => ['SHOW ENGINE ALL MUTEX'];
            yield 'InnoDB' => ['SHOW ENGINE INNODB MUTEX'];
            yield 'reset within input' => ["SET GLOBAL innodb_monitor_reset='latch'; SHOW ENGINE ALL MUTEX"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testObservationUsesResetCountersWithoutReplacingRows(string $sql): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $first = $target->compare($sql);
        $again = $target->compare($sql);
        $server->stop();

        self::assertFalse($first->volatile, (string) $first->referenceDifference);
        self::assertNull($first->difference, (string) $first->difference);
        self::assertSame([], $first->contracts);
        self::assertFalse($again->volatile, (string) $again->referenceDifference);
        self::assertNull($again->difference, (string) $again->difference);
        self::assertSame([], $again->contracts);
    }
}
