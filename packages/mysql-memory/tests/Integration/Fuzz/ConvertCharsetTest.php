<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ConvertCharsetTest extends TestCase
{
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
    public function testDirectActionsRetainTheirNameDiagnosticsAcrossTableCreation(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $nodes = array_map(static fn (array $names): AlterTable => new AlterTable(new QualifiedName(new Name('t')), [new ConvertCharset(new CharsetName(new Name($names[0])), new CollationName(new Name($names[1])))]), [['utf8mb4', 'utf8mb4_bin'], ['binary', 'EXECUTE'], ['missing', 'absent'], ['utf8mb4', 'utf8mb4_0900_ai_ci']]);
                $session->text = 'unrelated invalid input';
                $before = array_map(static fn (AlterTable $node): string => serialize((new Memory())->observe($session, $node)), $nodes);
                $expectedBefore = array_map(static fn (AlterTable $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()))), $nodes);
                $setup = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a VARCHAR(4))', "INSERT INTO t VALUES('abc')"];
                array_map($native->query(...), $setup);
                array_map($session->run(...), $setup);
                $session->text = 'unrelated invalid input';
                $after = array_map(static fn (AlterTable $node): string => serialize((new Memory())->observe($session, $node)), $nodes);
                $expectedAfter = array_map(static fn (AlterTable $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()))), $nodes);

                self::assertSame($expectedBefore, $before);
                self::assertSame($expectedAfter, $after);
                self::assertArrayNotHasKey('error', (new Memory())->observe($session, $nodes[0]));
                self::assertSame('unrelated invalid input', $session->text);
                self::assertNotSame($before[0], $after[0]);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

    #[DataProvider('providerReleases')]
    public function testWireDiagnosticsFollowNameOrderBeforeTableResolution(string $release): void
    {
        $statements = [
            'ALTER TABLE _sf CONVERT TO CHARSET BINARY COLLATE EXECUTE',
            'ALTER TABLE t CONVERT TO CHARSET missing COLLATE absent',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4',
            'CREATE DATABASE d', 'USE d',
            'ALTER TABLE _sf CONVERT TO CHARSET BINARY COLLATE EXECUTE',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4',
            'CREATE TABLE t(a VARCHAR(4))', "INSERT INTO t VALUES('abc')",
            'ALTER TABLE t CONVERT TO CHARSET missing COLLATE absent',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE missing',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_bin',
            'ALTER TABLE t CONVERT TO CHARSET DEFAULT',
            'ALTER TABLE t CONVERT TO CHARSET missing, ADD c INT',
            'ALTER TABLE t ADD c INT, CONVERT TO CHARSET missing',
            'ALTER TABLE t CONVERT TO CHARSET missing, ADD',
            'ALTER TABLE t CONVERT TO CHARSET missing COLLATE',
            "PREPARE p FROM 'ALTER TABLE t CONVERT TO CHARSET missing'",
            'CREATE PROCEDURE p() ALTER TABLE t CONVERT TO CHARSET missing',
            'CREATE PROCEDURE p() BEGIN IF 0 THEN ALTER TABLE t CONVERT TO CHARSET missing; END IF; END',
            'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; ALTER TABLE t CONVERT TO CHARSET missing; END',
            'ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin, ADD c VARCHAR(4) CHARSET missing',
            'CREATE PROCEDURE p() ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin',
            'CREATE PROCEDURE p() BEGIN ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin; ALTER TABLE t CONVERT TO CHARSET missing; END',
            'SELECT a, CHARSET(a), COLLATION(a) FROM t',
        ];
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
