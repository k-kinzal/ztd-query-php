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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class NativeReplicationStartupTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, list<M\Statement>}>
     */
    public static function providerStartup(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            $terms = M\Replication\Terminology::Legacy;
            $nodes = [
                new M\Replication\Replica\StopReplica($terms),
                new M\Replication\Source\ChangeReplicationSource($terms, [new M\Replication\Source\SourceOption($terms, M\Replication\Source\SourceOptionKind::ConnectRetry, new M\Literal\Numeral('0'))]),
                new M\Replication\Replica\StartReplica($terms, [M\Replication\Replica\ReplicaThread::Applier]),
                new M\Replication\Replica\StopReplica($terms, [M\Replication\Replica\ReplicaThread::Applier]),
                new M\Replication\Reset\Reset([new M\Replication\Reset\ResetReplica($terms, true)]),
            ];
            if ($release === '5.7.44') {
                $nodes[] = new M\Replication\Filter\ChangeReplicationFilter([new M\Replication\Filter\ReplicationFilter(M\Replication\Filter\FilterKind::DoDb, [])]);
            }

            foreach ([0, 1] as $initial) {
                yield $release . ':' . $initial => [$release, $initial, $nodes];
            }
        }
    }

    /**
     * @param list<M\Statement> $nodes Directly constructed commands reused in every state
     */
    #[DataProvider('providerStartup')]
    public function testTheSameStructuresRetainStartupAvailabilityAcrossCurrentIdChanges(string $release, int $initial, array $nodes): void
    {
        $container = Testcontainers::run((new DisposableMySql($release))->withCommands(['mysqld', '--server-id=' . $initial]));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release, ['server_id' => $initial]))->connect();
                $profile = $session->semantics()->profile();
                $observations = array_map(static function (int $current) use ($native, $session, $profile, $nodes): array {
                    $native->query('SET GLOBAL server_id=' . $current);
                    $session->query('SET GLOBAL server_id=' . $current);
                    $session->text = 'opaque non-SQL request label';

                    return array_map(static function (M\Statement $node) use ($native, $session, $profile): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $profile));
                        $actual = (new Memory())->observe($session, $node);

                        return [serialize($expected), serialize($actual), $actual['error'][0] ?? null];
                    }, $nodes);
                }, [1, 0, 2]);
                $flat = array_merge(...$observations);
                self::assertSame(array_column($flat, 0), array_column($flat, 1));
                self::assertSame(array_fill(0, 3, $initial === 0 ? 1794 : null), array_map(static fn (array $stage): ?int => $stage[1][2], $observations));
                self::assertSame($initial === 0 ? [1794, 1794, 1794] : ($release === '5.6.51' ? [1200, 1200, 1200] : [null, 1200, null]), array_map(static fn (array $stage): ?int => $stage[2][2], $observations));
                self::assertSame('opaque non-SQL request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

}
