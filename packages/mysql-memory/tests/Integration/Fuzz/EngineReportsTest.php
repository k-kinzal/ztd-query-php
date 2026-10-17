<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\EngineReports;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class EngineReportsTest extends TestCase
{
    public function testHandlesOnlyPhysicalEngineStatusStatements(): void
    {
        self::assertTrue(EngineReports::handles('SHOW ENGINE ALL STATUS;'));
        self::assertTrue(EngineReports::handles('show engine InnoDB status'));
        self::assertFalse(EngineReports::handles('SHOW STATUS'));
        self::assertFalse(EngineReports::handles('SHOW ENGINE ALL MUTEX'));
        self::assertFalse(EngineReports::handles("SELECT 'SHOW ENGINE ALL STATUS'"));
        self::assertFalse(EngineReports::handles('SHOW ENGINE ALL STATUS; SELECT 1'));
    }

    public function testNativeRequiresRecognizedPhysicalReports(): void
    {
        $contract = new EngineReports();
        self::assertTrue($contract->native([['InnoDB', '', 'monitor'], ['performance_schema', 'threads.count', '256']]));
        self::assertFalse($contract->native([]));
        self::assertFalse($contract->native([['performance_schema', 'threads.count', '-1']]));
        self::assertFalse($contract->native([['performance_schema', 'threads.count', 'invalid']]));
        self::assertFalse($contract->native([['other', '', 'monitor']]));
        self::assertFalse($contract->native([['InnoDB', '', '']]));
    }

    public function testComparableDoesNotHideRowsErrorsOrMetadataChanges(): void
    {
        $observation = ['results' => [['columns' => [['Type', '', 'VAR_STRING', 40], ['Name', '', 'VAR_STRING', 2048], ['Status', '', 'VAR_STRING', 40]], 'rows' => []]], 'warnings' => []];
        $contract = new EngineReports();
        $normalized = $contract->comparable($observation, true);
        self::assertSame([EngineReports::CONTRACT], $normalized['contracts']);
        $wrongRows = $observation;
        $wrongRows['results'][0]['rows'] = [['InnoDB', '', 'invented report']];
        self::assertSame($wrongRows, $contract->comparable($wrongRows, true));
        $error = $observation + ['error' => [1286, '42000', 'Unknown storage engine']];
        self::assertSame($error, $contract->comparable($error, true));
        $wrongName = $observation;
        $wrongName['results'][0]['columns'][0][0] = 'Wrong';
        self::assertSame($wrongName, $contract->comparable($wrongName, true));
        $changed = $observation;
        $changed['results'][0]['columns'][0][3] = 99;
        $changed['warnings'] = [['Warning', 1, 'unexpected']];
        self::assertSame($changed + ['contracts' => [EngineReports::CONTRACT]], $contract->comparable($changed, true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testComparableMatchesIsolatedServers(string $release): void
    {
        $first = Testcontainers::run(new DisposableMySql($release));
        $server = null;
        try {
            $second = Testcontainers::run(new DisposableMySql($release));
            try {
                $left = $first->getData(Endpoint::class);
                $right = $second->getData(Endpoint::class);
                $native = new PDO('mysql:host=' . $left->host . ';port=' . $left->port, $left->username, $left->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $another = new PDO('mysql:host=' . $right->host . ';port=' . $right->port, $right->username, $right->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $server = Server::start($release);
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $contract = new EngineReports();
                $sqls = ['SHOW ENGINE ALL STATUS', 'SHOW ENGINE InnoDB STATUS', 'SHOW ENGINE performance_schema STATUS'];
                $expected = array_map(static fn (string $sql): string => serialize($contract->comparable($observer->observe($native, $sql, true), false)), $sqls);
                $again = array_map(static fn (string $sql): string => serialize($contract->comparable($observer->observe($another, $sql, true), false)), $sqls);
                $actual = array_map(static fn (string $sql): string => serialize($contract->comparable($observer->observe($memory, $sql, true), true)), $sqls);
                self::assertSame([true, true, true], array_map(static fn (string $result): bool => str_contains($result, EngineReports::CONTRACT), $actual));
                self::assertSame($expected, $again, $release);
                self::assertSame($expected, $actual, $release);
            } finally {
                $second->stop();
            }
        } finally {
            $server?->stop();
            $first->stop();
        }
    }
}
