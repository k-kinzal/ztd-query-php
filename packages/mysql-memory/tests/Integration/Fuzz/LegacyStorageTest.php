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
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LegacyStorageTest extends TestCase
{
    public function testLegacyEnginesAndStorageHistoriesMatchNative(): void
    {
        $statements = [
            "ALTER TABLESPACE absent ADD DATAFILE 'x.ibd'", "ALTER TABLESPACE absent ADD DATAFILE 'x.ibd' ENGINE missing",
            "ALTER TABLESPACE absent ADD DATAFILE 'x.ibd' ENGINE MyISAM", 'DROP TABLESPACE absent', 'DROP TABLESPACE absent ENGINE missing', 'DROP TABLESPACE absent ENGINE InnoDB',
            "CREATE LOGFILE GROUP g ADD UNDOFILE 'x.dat'", "ALTER LOGFILE GROUP g ADD UNDOFILE 'x.dat'", 'DROP LOGFILE GROUP g ENGINE InnoDB',
            "CREATE TABLESPACE s ADD DATAFILE 'x'", "CREATE TABLESPACE s ADD DATAFILE 'x.ibd' ENGINE InnoDB",
            "ALTER TABLESPACE s ADD DATAFILE 'y.ibd'", "ALTER TABLESPACE s DROP DATAFILE 'x.ibd'", 'DROP TABLESPACE s ENGINE InnoDB',
            "CREATE TABLESPACE s ADD DATAFILE 'x.ibd' ENGINE InnoDB", 'DROP TABLESPACE s',
            "SET sql_mode=''", "CREATE TABLESPACE s ADD DATAFILE 'x' ENGINE missing", "ALTER TABLESPACE absent ADD DATAFILE 'x.ibd' ENGINE missing", 'DROP TABLESPACE absent ENGINE missing',
        ];
        $container = Testcontainers::run(new DisposableMySql('5.7.44'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start('5.7.44');
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    public function testSameDropStructureRespondsToTablespaceCreation(): void
    {
        $node = new DropTablespace(new Name('s'));
        $container = Testcontainers::run(new DisposableMySql('5.7.44'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance('5.7.44'))->connect();
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $observed = array_map(static function (?string $fixture) use ($native, $session, $node, $sql): array {
                    if ($fixture !== null) {
                        $native->query($fixture);
                        $session->query($fixture);
                    }
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);

                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, [null, "CREATE TABLESPACE s ADD DATAFILE 'x.ibd'", null]);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1529, null, 1529], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
