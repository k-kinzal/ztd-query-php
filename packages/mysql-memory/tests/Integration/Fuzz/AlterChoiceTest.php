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
final class AlterChoiceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerChoices(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'CREATE TABLE d.t(a INT)'];
            $invalid = ['ALGORITHM=bad', 'LOCK=bad', ...(str_starts_with($release, '5.') ? ['ALGORITHM=INSTANT'] : ['ALGORITHM=`DEFAULT`', 'LOCK=`DEFAULT`'])];
            foreach ($invalid as $option) {
                foreach (['', ', CHANGE a a LONGTEXT ASCII BINARY', ' TRAILING'] as $tail) {
                    $statements[] = 'ALTER TABLE d.t ' . $option . $tail;
                }
                array_push($statements, 'CREATE INDEX i ON d.t(a) ' . $option, 'DROP INDEX i ON d.t ' . $option, 'CREATE PROCEDURE d.p() ALTER TABLE d.t ' . $option);
            }
            array_push($statements, 'ALTER TABLE d.t CHANGE a a LONGTEXT ASCII BINARY, ALGORITHM=bad', 'ALTER TABLE d.t ALGORITHM=bad, LOCK=bad', 'ALTER TABLE d.t LOCK=bad, ALGORITHM=bad');
            foreach (['DEFAULT', 'COPY', 'INPLACE', ...(str_starts_with($release, '5.') ? ['`DEFAULT`'] : ['INSTANT'])] as $algorithm) {
                $statements[] = 'ALTER TABLE d.t ALGORITHM=' . $algorithm;
            }
            foreach (['DEFAULT', 'NONE', 'SHARED', 'EXCLUSIVE', ...(str_starts_with($release, '5.') ? ['`DEFAULT`'] : [])] as $lock) {
                array_push($statements, 'ALTER TABLE d.t ADD INDEX i(a), LOCK=' . $lock, 'ALTER TABLE d.t DROP INDEX i');
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Known option domains and input refusal ordering
     */
    #[DataProvider('providerChoices')]
    public function testModifierInputRefusalsMatchNative(string $release, array $statements): void
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
