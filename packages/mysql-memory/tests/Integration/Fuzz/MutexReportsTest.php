<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\EngineReports;
use Fuzz\Target\MutexReports;
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
final class MutexReportsTest extends TestCase
{
    public function testHandlesOnlySingleInnoDbMutexReports(): void
    {
        self::assertTrue(MutexReports::handles('SHOW ENGINE ALL MUTEX;'));
        self::assertTrue(MutexReports::handles('show engine InnoDB mutex'));
        self::assertFalse(MutexReports::handles('SHOW ENGINE ALL STATUS'));
        self::assertFalse(MutexReports::handles('SHOW STATUS'));
        self::assertFalse(MutexReports::handles('SHOW ENGINE ALL MUTEX; SELECT 1'));
    }

    public function testNativeRequiresNamedNonnegativePhysicalCounters(): void
    {
        $contract = new MutexReports();
        self::assertTrue($contract->native([]));
        self::assertTrue($contract->native([['InnoDB', 'sum rwlock: buf0buf.cc:857', 'waits=1']]));
        self::assertTrue($contract->native([['InnoDB', 'mutex', 'spins=0,waits=2,calls=3']]));
        self::assertFalse($contract->native([['InnoDB', 'mutex', 'waits=-1']]));
        self::assertFalse($contract->native([['InnoDB', 'mutex', 'waits=1 waits=2']]));
        self::assertFalse($contract->native([['InnoDB', 'mutex', 'queries=1']]));
        self::assertFalse($contract->native([['InnoDB', '', 'waits=1']]));
        self::assertFalse($contract->native([['other', 'mutex', 'waits=1']]));
    }

    public function testComparableRetainsDiagnosticsAndRejectsInventedMemoryRows(): void
    {
        $empty = ['results' => [['columns' => [['Type', '', 'VAR_STRING', 40], ['Name', '', 'VAR_STRING', 2048], ['Status', '', 'VAR_STRING', 40]], 'rows' => []]], 'warnings' => []];
        $native = $empty;
        $native['results'][0]['rows'] = [['InnoDB', 'sum rwlock: buf0buf.cc:857', 'waits=1']];
        $contract = new EngineReports();

        self::assertSame($contract->comparable($empty, true, true), $contract->comparable($native, false, true));
        self::assertSame($native, $contract->comparable($native, true, true));
        $error = $native + ['error' => [1286, '42000', 'Unknown storage engine']];
        self::assertSame($error, $contract->comparable($error, false, true));
        $changed = $empty;
        $changed['results'][0]['columns'][0][3] = 99;
        $changed['warnings'] = [['Warning', 1, 'unexpected']];
        self::assertSame($changed + ['contracts' => [MutexReports::CONTRACT]], $contract->comparable($changed, true, true));
    }

    public function testDirectObservationUsesTheSamePhysicalContract(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex();
        $columns = array_map(static fn (string $name): array => ['name' => $name, 'orgname' => '', 'type' => 253], ['Type', 'Name', 'Status']);
        $empty = ['results' => [['columns' => $columns, 'rows' => []]], 'warnings' => []];
        $native = $empty;
        $native['results'][0]['rows'] = [['InnoDB', 'sum rwlock: buf0buf.cc:857', 'waits=1']];
        $contract = new \Fuzz\Structure\Audit\EngineReport();

        self::assertSame($contract->comparable($node, $empty, true), $contract->comparable($node, $native, false));
        self::assertSame($native, $contract->comparable($node, $native, true));
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
    public function testComparableValidatesIndependentNativeReportsAndMemory(string $release): void
    {
        $definitions = array_map(static function (int $offset) use ($release): DisposableMySql {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_CONTAINER_PORT'), FILTER_VALIDATE_INT);
            if (is_int($port)) {
                $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port + $offset));
            }

            return $definition;
        }, [0, 1]);
        $first = Testcontainers::run($definitions[0]);
        $server = null;
        try {
            $second = Testcontainers::run($definitions[1]);
            try {
                $left = $first->getData(Endpoint::class);
                $right = $second->getData(Endpoint::class);
                $native = new PDO('mysql:host=' . $left->host . ';port=' . $left->port, $left->username, $left->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $another = new PDO('mysql:host=' . $right->host . ';port=' . $right->port, $right->username, $right->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $server = Server::start($release);
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $contract = new EngineReports();
                $sqls = ['SHOW ENGINE ALL MUTEX', 'SHOW ENGINE InnoDB MUTEX'];
                $expected = array_map(static fn (string $sql): string => serialize($contract->comparable($observer->observe($native, $sql, true), false, true)), $sqls);
                $again = array_map(static fn (string $sql): string => serialize($contract->comparable($observer->observe($another, $sql, true), false, true)), $sqls);
                $actual = array_map(static fn (string $sql): string => serialize($contract->comparable($observer->observe($memory, $sql, true), true, true)), $sqls);

                self::assertSame([true, true], array_map(static fn (string $result): bool => str_contains($result, MutexReports::CONTRACT), $actual));
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
