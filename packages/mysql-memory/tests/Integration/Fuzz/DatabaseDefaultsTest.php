<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabaseDefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            $statements = ['CREATE DATABASE d', 'CREATE DATABASE IF NOT EXISTS d', 'ALTER DATABASE COLLATE DEFAULT'];
            foreach (['missing', 'a b', 'a-b', 'é猫', 'CON', '#mysql50#a-b'] as $name) {
                $name = '`' . $name . '`';
                array_push($statements, 'ALTER DATABASE ' . $name . ' COLLATE missing', 'ALTER DATABASE ' . $name . ' COLLATE DEFAULT', 'CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', 'ALTER DATABASE ' . $name . ' COLLATE DEFAULT', 'SHOW CREATE DATABASE ' . $name, 'ALTER DATABASE ' . $name . ' CHARACTER SET DEFAULT', 'SHOW CREATE DATABASE ' . $name, 'DROP DATABASE ' . $name, 'ALTER DATABASE ' . $name . ' COLLATE DEFAULT');
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Database creation, default resolution and removal histories
     */
    #[DataProvider('providerReleases')]
    public function testDatabaseNamesDefaultsAndConditionsFollowNativeHistory(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
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
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerLegacyNames(): iterable
    {
        $choices = new Choices('database-definition-state-20261017');
        foreach (['5.6.51', '5.7.44'] as $release) {
            $names = [];
            for ($trial = 0; $trial < 10; $trial++) {
                $names[] = 't' . $choices->integer(0, 100000);
            }
            yield $release => [$release, $names];
        }
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('providerLegacyNames')]
    public function testTheSameAlterationTracksAbsentCreatedAndDroppedSchemas(string $release, array $names): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release))->connect();
            $session->query('CREATE DATABASE d; USE d');
            $observations = array_map(static function (string $name) use ($session, $native): array {
                $statement = new AlterDatabase(new Name($name), [new DatabaseCollation(new CollationName(null))]);
                $sql = (new \Fuzz\Structure\Reference())->sql($statement, $session->semantics()->profile());
                return array_map(static function (array $history) use ($statement, $sql, $session, $native): array {
                    array_map(static fn (string $setup): mysqli_result|bool => $native->query($setup), $history);
                    array_map($session->query(...), $history);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $statement)];
                }, [[], ['CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', 'CREATE TABLE ' . $name . '.t(a INT)', 'INSERT INTO ' . $name . '.t VALUES(1),(2)'], ['DROP DATABASE ' . $name]]);
            }, $names);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame(array_merge(...array_fill(0, count($names), [1, null, 1])), array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $flat));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
