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
final class JsonAttributeDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE placement'];
        foreach (['', '{}', 'null', '[]', 'true', '1', '"x"', '1e-999999', ' ', 'bad', '{', '[', '01', '-x', '1.', '1e', '1e309', '{"x":NaN}', '"\uD800"', '"\uDC00"', '"\uD800\uDC00"', str_repeat('[', 100) . '0' . str_repeat(']', 100), str_repeat('[', 101) . '0' . str_repeat(']', 101), str_repeat('[', 513) . '0' . str_repeat(']', 513)] as $document) {
            $quoted = "'" . str_replace(['\\', "'"], ['\\\\', "''"], $document) . "'";
            $statements[] = 'ALTER TABLE placement.missing ENGINE_ATTRIBUTE ' . $quoted;
        }
        $statements = [...$statements,
            "CREATE TABLE placement.t (a INT ENGINE_ATTRIBUTE 'bad')",
            "CREATE TABLE placement.t (a INT, KEY(a) SECONDARY_ENGINE_ATTRIBUTE 'bad')",
            "CREATE TABLE placement.t (a INT) ENGINE_ATTRIBUTE 'bad'",
            "CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd' ENGINE_ATTRIBUTE 'bad'",
            "CREATE PROCEDURE placement.p() CREATE TABLE t(a INT ENGINE_ATTRIBUTE 'bad')",
            "CREATE PROCEDURE placement.p() BEGIN IF FALSE THEN CREATE TABLE t(a INT ENGINE_ATTRIBUTE 'bad'); END IF; END",
            "CREATE TABLE placement.t (a INT ENGINE_ATTRIBUTE 'bad', b INT DEFAULT ?)",
            "CREATE TABLE placement.t (a INT DEFAULT ?, b INT ENGINE_ATTRIBUTE 'bad')",
            "CREATE TABLE placement.t (a INT) ENGINE_ATTRIBUTE 'bad' +",
            "CREATE TABLE placement.t (a INT ENGINE_ATTRIBUTE 'bad', b INT DEFAULT (!1))",
            "CREATE TABLE placement.t (a INT DEFAULT (!1), b INT ENGINE_ATTRIBUTE 'bad')",
            "CREATE TABLE placement.t (a INT ENGINE_ATTRIBUTE '', b INT ENGINE_ATTRIBUTE 'null', KEY(a) ENGINE_ATTRIBUTE '[]') ENGINE_ATTRIBUTE '{}' SECONDARY_ENGINE_ATTRIBUTE '1'",
            "CREATE TABLE placement.t (a INT SECONDARY_ENGINE_ATTRIBUTE '', b INT SECONDARY_ENGINE_ATTRIBUTE 'null', KEY(a) SECONDARY_ENGINE_ATTRIBUTE '[]') SECONDARY_ENGINE_ATTRIBUTE '1'",
            'INSERT INTO placement.t VALUES (1, 2)',
            "ALTER TABLE placement.t ENGINE_ATTRIBUTE '{\"x\":1}'",
            "ALTER TABLE placement.t ADD COLUMN c INT ENGINE_ATTRIBUTE '{}'",
            "CREATE INDEX primary_attribute ON placement.t(b) ENGINE_ATTRIBUTE '{}'",
            "ALTER TABLE placement.t ENGINE=MyISAM ENGINE_ATTRIBUTE '{}'",
            'SELECT * FROM placement.t',
            "CREATE INDEX i ON placement.t(b) SECONDARY_ENGINE_ATTRIBUTE 'true'",
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The same state history and operand boundaries
     */
    #[DataProvider('providerReleases')]
    public function testDefinitionTimeJsonDomainAndDiagnosticOrder(string $release, array $statements): void
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
