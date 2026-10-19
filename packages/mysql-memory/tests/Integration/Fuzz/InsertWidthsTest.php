<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class InsertWidthsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws JsonException When the saved input corpus is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/insert-widths.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Written row counts, target and value dependencies, and stored execution histories
     */
    #[DataProvider('providerReleases')]
    public function testWrittenWidthsPreserveDependencyPriorityAndSkippedStoredInstructions(string $release, array $statements): void
    {
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

    #[DataProvider('providerVersions')]
    public function testTheSameConstructedRowsFollowDestinationStateWithoutParsing(string $release): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows(
            new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('d')))),
            [new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('7')]), new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('8')])],
        );
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $session = (new \MySqlMemory\Instance($release, [], ['d']))->connect();
            $session->text = 'unrelated invalid input';
            $missing = (new \Fuzz\Structure\Audit\Memory())->observe($session, $node);
            self::assertSame((new Native())->observe($native, 'INSERT INTO d.t VALUES (7), (8)'), $missing);
            self::assertSame(1146, $missing['error'][0] ?? null);
            $native->query('CREATE TABLE d.t(a INT, b INT)');
            $session->query('CREATE TABLE d.t(a INT, b INT)');
            $session->text = 'unrelated invalid input';
            $wide = (new \Fuzz\Structure\Audit\Memory())->observe($session, $node);
            self::assertSame((new Native())->observe($native, 'INSERT INTO d.t VALUES (7), (8)'), $wide);
            self::assertSame(1136, $wide['error'][0] ?? null);
            $native->query('ALTER TABLE d.t DROP b');
            $session->query('ALTER TABLE d.t DROP b');
            $session->text = 'unrelated invalid input';
            $written = (new \Fuzz\Structure\Audit\Memory())->observe($session, $node);
            self::assertSame((new Native())->observe($native, 'INSERT INTO d.t VALUES (7), (8)'), $written);
            self::assertArrayNotHasKey('error', $written);
            self::assertSame('unrelated invalid input', $session->text);
            $nativeRows = $native->query('SELECT a FROM d.t ORDER BY a');
            self::assertInstanceOf(mysqli_result::class, $nativeRows);
            self::assertSame([['7'], ['8']], $nativeRows->fetch_all());
            $memoryRows = $session->query('SELECT a FROM d.t ORDER BY a')[0];
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $memoryRows);
            self::assertSame([['7'], ['8']], $memoryRows->rows);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
