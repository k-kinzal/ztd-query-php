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
final class KillWindowPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['', 'ROWS UNBOUNDED PRECEDING EXCLUDE CURRENT ROW', 'ROWS UNBOUNDED PRECEDING EXCLUDE GROUP', 'ROWS UNBOUNDED PRECEDING EXCLUDE TIES', 'ROWS UNBOUNDED PRECEDING EXCLUDE NO OTHERS', 'GROUPS UNBOUNDED PRECEDING'] as $frame) {
            foreach (['ROW_NUMBER()', 'SUM(1)', 'FIRST_VALUE(1) IGNORE NULLS'] as $call) {
                $expression = $call . ' OVER (' . $frame . ')';
                $statements[] = 'KILL ' . $expression;
                $statements[] = 'KILL (SELECT ' . $expression . ')';
                $statements[] = 'KILL USER() = (USER() MEMBER ((' . $expression . '))) IS TRUE';
            }
        }
        foreach (['SELECT * LIMIT 1', 'SELECT 1,2 LIMIT 1', 'SELECT missing LIMIT 1'] as $body) {
            $statements[] = 'KILL USER() = ALL (' . $body . ') IS TRUE';
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Direct and subquery windows and retained LIMIT restrictions
     */
    #[DataProvider('providerInputs')]
    public function testWindowPlacementPrecedesFrameRestrictionsOutsideQueryBlocks(string $release, array $statements): void
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
