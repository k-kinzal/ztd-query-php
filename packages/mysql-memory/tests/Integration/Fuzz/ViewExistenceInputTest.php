<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ViewExistenceInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $inputs = [];
        foreach (['ALTER VIEW', 'ALTER ALGORITHM=MERGE DEFINER=CURRENT_USER SQL SECURITY INVOKER VIEW', 'CREATE OR REPLACE VIEW', 'CREATE VIEW'] as $head) {
            foreach (['SELECT 1', 'SELECT ?', "SELECT DATE 'bad'", 'SELECT BINARY @@missing', 'SELECT ('] as $body) {
                $inputs[] = "$head IF NOT EXISTS v AS $body";
            }
        }
        $inputs[] = 'CREATE PROCEDURE p() ALTER VIEW IF NOT EXISTS v AS SELECT ?';
        $inputs[] = 'CREATE PROCEDURE p() BEGIN SELECT BINARY 1; ALTER VIEW IF NOT EXISTS v AS SELECT ?; END';
        $inputs[] = (string) file_get_contents(__DIR__ . '/fixtures/view-existence-order.sql');
        foreach (['ALTER VIEW', 'CREATE OR REPLACE VIEW'] as $head) {
            foreach (['IF', 'IF NOT', 'IF NOT EXISTS', 'IF NOT bad', 'IF NOT EXISTS )', 'IF NOT EXISTS ?', "IF NOT EXISTS 'x'", 'IF NOT EXISTS .t', 'IF NOT EXISTS d.', 'IF NOT EXISTS d.x', 'IF NOT EXISTS v(', 'IF NOT EXISTS v SELECT', 'IF NOT EXISTS v AS ?'] as $tail) {
                $inputs[] = "$head $tail";
            }
        }
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $statements = [...$inputs, 'CREATE DATABASE d', 'USE d', ...$inputs, 'SELECT * FROM v', 'DROP VIEW IF EXISTS v', 'CREATE VIEW v AS SELECT 2 AS x', ...$inputs, 'SELECT * FROM v'];
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements View headers, body failures and catalog histories
     */
    #[DataProvider('providerReleases')]
    public function testExistenceClausesKeepTheirCommandRoleBeforeLaterInputs(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);
                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    public function testConditionalViewStructurePreservesExistingObjectsAcrossHistories(): void
    {
        $container = Testcontainers::run(new DisposableMySql('9.1.0'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new \MySqlMemory\Instance('9.1.0'))->connect();
                $node = new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('7'), new \SqlSemantics\Statement\Identifier\Name('x'))]), ifNotExists: true));
                $sql = 'CREATE VIEW IF NOT EXISTS v AS SELECT 7 AS x';
                array_map(static function (string|\SqlSemantics\Platform\MySql\Statement\View\CreateView $step) use ($native, $session, $sql): void {
                    $statement = is_string($step) ? $session->analyze($step)->statement : $step;
                    self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
                    $expected = (new Native())->observe($native, is_string($step) ? $step : $sql);
                    $session->text = 'opaque conditional view request';
                    self::assertSame($expected, (new \Fuzz\Structure\Audit\Memory())->observe($session, $statement));
                }, ['CREATE DATABASE d', 'USE d', $node, 'SELECT * FROM v', 'CREATE OR REPLACE VIEW v AS SELECT 2 AS x', $node, 'SELECT * FROM v', 'DROP VIEW v', 'CREATE TABLE v(x INT)', 'INSERT INTO v VALUES (5)', $node, 'SELECT * FROM v', 'DROP TABLE v', $node, 'SELECT * FROM v']);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
