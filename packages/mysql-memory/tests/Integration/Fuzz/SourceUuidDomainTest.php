<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Statement;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SourceUuidDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<Statement>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_UUID_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new StaticPortStrategy($port));
            }
            $nodes = [];
            $values = [AnonymousGtids::Off, AnonymousGtids::Local, new Text('3e11fa47-71ca-11e1-9e33-c80aa9429562'), new Text('3E11FA47-71CA-11E1-9E33-C80AA9429562'), new Text('00000000-0000-0000-0000-000000000000'), new Text('ffffffff-ffff-ffff-ffff-ffffffffffff'), new Text('3e11fa47-71ca-11e1-9e33-c80aa9429562 trailing'), new Text("3e11fa47-71ca-11e1-9e33-c80aa9429562\0tail"), new Text("3e11fa47-71ca-11e1-9e33-c80aa9429562\nrest")];
            foreach ($values as $value) {
                $nodes[] = new ChangeReplicationSource(Terminology::Current, [new SourceOption(Terminology::Current, SourceOptionKind::AssignGtidsToAnonymousTransactions, $value)]);
            }
            yield $release => [$release, $definition, $nodes];
        }
    }

    /**
     * @param list<Statement> $nodes The same UUID setting objects with GTID assignment disabled and enabled
     */
    #[DataProvider('providerStructures')]
    public function testSameUuidStructuresHaveStateDependentOutcomes(string $release, DisposableMySql $definition, array $nodes): void
    {
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $stages = [[], ['RESET REPLICA ALL', 'SET GLOBAL enforce_gtid_consistency=ON', 'SET GLOBAL gtid_mode=OFF_PERMISSIVE', 'SET GLOBAL gtid_mode=ON_PERMISSIVE', 'SET GLOBAL gtid_mode=ON'], ['RESET REPLICA ALL', 'SET GLOBAL gtid_mode=ON_PERMISSIVE', 'SET GLOBAL gtid_mode=OFF_PERMISSIVE', 'SET GLOBAL gtid_mode=OFF']];
                $results = array_map(static function (array $setup) use ($native, $session, $nodes): array {
                    array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $setup);
                    array_map($session->run(...), $setup);
                    $profile = $session->semantics()->profile();
                    $observations = array_map(/** @return array{string, bool} */ static function (Statement $node) use ($native, $profile): array {
                        $observation = (new Native())->observe($native, (new Reference())->sql($node, $profile));

                        return [serialize($observation), !isset($observation['error']) && count($observation['results']) === 1];
                    }, $nodes);
                    $expected = array_column($observations, 0);
                    $completed = array_column($observations, 1);
                    $session->text = 'unrelated invalid input';
                    $actual = array_map(static fn (Statement $node): string => serialize((new Memory())->observe($session, $node)), $nodes);

                    return [$expected, $actual, $completed];
                }, $stages);

                self::assertSame(array_column($results, 0), array_column($results, 1));
                self::assertSame('unrelated invalid input', $session->text);
                self::assertSame([true, ...array_fill(0, 8, false)], $results[0][2]);
                self::assertSame(array_fill(0, 9, true), $results[1][2]);
                self::assertSame($results[0][2], $results[2][2]);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
