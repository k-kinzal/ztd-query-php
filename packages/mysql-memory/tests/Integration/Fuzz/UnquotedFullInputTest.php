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
final class UnquotedFullInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d'];
        foreach (['FULL', 'full', '`FULL`', 't.FULL', 'FULL.x', 'FULL .x'] as $name) {
            array_push($statements, 'SELECT ' . $name . ' FROM', 'SELECT ' . $name . ', CAST(1 AT LOCAL AS DATETIME)', 'SELECT CAST(1 AT LOCAL AS DATETIME), ' . $name, 'SHOW COLLATION WHERE ' . $name . ' COLLATE missing = CAST(1 AT LOCAL AS DATETIME)', 'CREATE PROCEDURE p() BEGIN SELECT ' . $name . '; SELECT CAST(1 AT LOCAL AS DATETIME); END');
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_FULL_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
            }
            yield $release => [$release, $definition, $statements];
        }
    }

    /**
     * @param list<string> $statements Identifier spellings before, after and inside input refusals
     */
    #[DataProvider('providerInputs')]
    public function testIdentifierWarningsSurviveInputRefusals(string $release, DisposableMySql $definition, array $statements): void
    {
        $container = Testcontainers::run($definition);
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
