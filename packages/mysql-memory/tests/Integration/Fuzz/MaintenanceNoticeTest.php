<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
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
final class MaintenanceNoticeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach ([false, true] as $created) {
            if ($created) {
                array_push($statements, 'CREATE TABLE `full`(a INT)', 'CREATE TABLE t(a INT)', 'CREATE VIEW v AS SELECT 1 AS a');
            }
            foreach (['ANALYZE TABLE', 'CHECK TABLE', 'REPAIR TABLE', 'OPTIMIZE TABLE', 'CACHE INDEX', 'LOAD INDEX INTO CACHE'] as $operation) {
                foreach (['full', 't, full', 'full, t', 'full, full', '`full`, t', 'full, missing', 'v, full', 'absent.full, t'] as $names) {
                    $statements[] = $operation . ' ' . $names . ($operation === 'CACHE INDEX' ? ' IN DEFAULT' : '');
                }
            }
        }
        array_push($statements, 'ANALYZE TABLE full UPDATE HISTOGRAM ON a', 'ANALYZE TABLE full, t UPDATE HISTOGRAM ON a', 'ALTER TABLE full CHECK PARTITION ALL');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Missing and populated tables, repeated targets, histograms and partitions
     */
    #[DataProvider('providerStatements')]
    public function testInputWarningsBecomeMaintenanceRowsAcrossTableStates(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
