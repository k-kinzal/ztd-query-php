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
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeleteBindingTest extends TestCase
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
    public function testTargetsChooseTheWrittenAndLockedOccurrence(string $release): void
    {
        $statements = [
            'CREATE DATABASE d', 'CREATE DATABASE e', 'CREATE TABLE d.t(a INT)', 'CREATE TABLE e.t(a INT)',
            'DELETE t FROM d.t', 'DELETE d.t FROM d.t', 'DELETE x FROM d.t AS x',
            'USE e', 'DELETE t FROM d.t', 'DELETE x FROM d.t AS x',
            'INSERT INTO d.t VALUES (1)', 'INSERT INTO e.t VALUES (2)',
            'DELETE t FROM d.t, e.t', 'SELECT * FROM d.t', 'SELECT * FROM e.t',
            'INSERT INTO e.t VALUES (2)', 'DELETE d.t FROM e.t, d.t', 'SELECT * FROM d.t', 'SELECT * FROM e.t',
            'INSERT INTO d.t VALUES (1)', 'LOCK TABLES d.t READ, e.t WRITE',
            'DELETE t FROM d.t, e.t', 'SELECT * FROM d.t', 'SELECT * FROM e.t', 'UNLOCK TABLES',
            'INSERT INTO e.t VALUES (2)', 'LOCK TABLES d.t WRITE, e.t READ',
            'DELETE d.t FROM e.t, d.t', 'SELECT * FROM d.t', 'SELECT * FROM e.t', 'UNLOCK TABLES',
            'USE d', 'DELETE d.t FROM d.t AS t', 'DELETE d.x FROM d.t AS x',
            'DELETE t, q FROM e.t, e.q', 'DELETE t FROM e.t AS t',
            'DELETE T FROM d.t AS T', 'DELETE T FROM d.t AS t',
            'CREATE TEMPORARY TABLE tmp(a INT)', 'INSERT INTO tmp VALUES (7)',
            'START TRANSACTION READ ONLY', 'DELETE x FROM tmp AS x', 'SELECT * FROM tmp', 'ROLLBACK',
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

    #[DataProvider('providerReleases')]
    public function testSameTargetRespondsToTheCurrentDatabase(string $release): void
    {
        $node = new MultipleDelete(null, [], [new QualifiedName(new Name('t'))], MultipleDeleteForm::BeforeFrom, [new TableReference(new QualifiedName(new Name('t'), new Name('d')))]);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $setup = ['CREATE DATABASE d', 'CREATE DATABASE e', 'CREATE TABLE d.t(a INT)', 'INSERT INTO d.t VALUES (1)'];
                array_map(static fn (string $sql): array => [$native->query($sql), $session->query($sql)], $setup);
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $observed = array_map(static function (string $database) use ($native, $session, $node, $sql): array {
                    $native->select_db($database);
                    $session->query('USE ' . $database);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);

                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, ['e', 'd', 'e', 'd']);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1109, null, 1109, null], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
