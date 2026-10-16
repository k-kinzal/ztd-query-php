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
final class LegacyFileNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $names = ['a.b', 'a-b', 'a b', 'a@b', 'a$b', 'a_b', "a'b", 'a`b', 'a/b', 'a\\b', 'a%b', 'a:b', 'a#b', 'あ', '表', 'é', 'É', 'ä', 'Ä', 'α', 'Α', 'я', 'Я', 'con', 'NUL', 'nul', 'lpt1', '#mysql50#a.b', '#mysql50#abc', '#mysql50#a-b', '#mysql50#a b', '#mysql50#a@b', '#mysql50#a/b', '#mysql50#a\\b', '#mysql50#con', '#mysql50#é', '#mysql50#あ', '#mysql50#', '#MYSQL50#a.b', 'com0', 'com1', 'com9', 'com10', 'lpt0', 'LPT9', 'con.txt', 'con ', 'CON.'];
        $statements = ['CREATE DATABASE d', 'USE d', ...array_map(static fn (string $name): string => 'RENAME TABLE `' . str_replace('`', '``', $name) . '` TO other', $names), 'CREATE DATABASE `d.a`', 'USE `d.a`', 'RENAME TABLE `1.5` TO `PRIMARY`, t.`あ` TO x.utf8mb4', 'RENAME TABLE `con`.t TO d.other', 'RENAME TABLE `from ` TO `to `', 'RENAME TABLE `from ` TO other', 'RENAME TABLE `a ` TO b, c TO `d `', 'RENAME TABLE a TO `b `, `c ` TO d', 'RENAME TABLE `` TO other'];
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The original counterexample and independent identifier cases
     */
    #[DataProvider('providerStatements')]
    public function testMissingTableDiagnosticsEncodeTheOriginalIdentifiers(string $release, array $statements): void
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
