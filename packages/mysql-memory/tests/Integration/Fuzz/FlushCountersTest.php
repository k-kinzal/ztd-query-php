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
final class FlushCountersTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'all tables' => ['FLUSH TABLES'];
        yield 'local flush' => ['FLUSH LOCAL TABLES'];
        yield 'global read lock' => ['FLUSH TABLES WITH READ LOCK; UNLOCK TABLES'];
        yield 'named tables' => ['FLUSH TABLES t1'];
        yield 'named read lock' => ['FLUSH TABLES t1 WITH READ LOCK; UNLOCK TABLES'];
        yield 'export' => ['FLUSH TABLES t1 FOR EXPORT; UNLOCK TABLES'];
        yield 'status retains total' => ['FLUSH TABLES; FLUSH STATUS'];
        yield 'other flush kinds' => ['FLUSH STATUS; FLUSH PRIVILEGES; FLUSH LOGS; FLUSH ENGINE LOGS'];
        yield 'missing table' => ['FLUSH TABLES missing'];
        $version = (string) getenv('MYSQL_VERSION');
        if (str_starts_with($version, '5.') || str_starts_with($version, '8.0.')) {
            yield 'reset binary logs' => ['RESET MASTER'];
            yield 'repeated reset target' => ['RESET MASTER, MASTER'];
        } else {
            yield 'reset binary logs' => ['RESET BINARY LOGS AND GTIDS'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTableFlushCountsMatchTheServerInBothScopes(string $sql): void
    {
        [$target] = Servers::shared();
        $table = str_starts_with($target->version, '5.6.') ? 'information_schema.GLOBAL_STATUS' : 'performance_schema.global_status';
        $session = str_starts_with($target->version, '5.6.') ? 'information_schema.SESSION_STATUS' : 'performance_schema.session_status';
        $comparison = $target->compare("SELECT CAST(VARIABLE_VALUE AS UNSIGNED) INTO @before FROM {$table} WHERE VARIABLE_NAME='Flush_commands'; {$sql}; SELECT CAST(VARIABLE_VALUE AS UNSIGNED)-@before AS global_delta FROM {$table} WHERE VARIABLE_NAME='Flush_commands'; SELECT CAST(VARIABLE_VALUE AS UNSIGNED)-@before AS session_delta FROM {$session} WHERE VARIABLE_NAME='Flush_commands'");

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
