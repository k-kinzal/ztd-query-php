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
final class NativeUntilHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Statement>}>
     */
    public static function providerHistories(): iterable
    {
        $terms = M\Replication\Terminology::Current;
        $stop = new M\Replication\Replica\StopReplica($terms);
        $reset = new M\Replication\Reset\Reset([new M\Replication\Reset\ResetReplica($terms, true)]);
        $receiver = new M\Replication\Replica\StartReplica($terms, [M\Replication\Replica\ReplicaThread::Receiver]);
        $nodes = [];
        foreach (M\Replication\Replica\UntilPoint::cases() as $point) {
            $until = new M\Replication\Replica\ReplicaUntil($point, $point === M\Replication\Replica\UntilPoint::AfterGaps ? null : new M\Literal\Text(''));
            $start = new M\Replication\Replica\StartReplica($terms, [M\Replication\Replica\ReplicaThread::Applier], $until);
            $nodes = [...$nodes, $stop, $reset, $start, $receiver, $start, $stop, $reset, $start];
        }
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<M\Statement> $nodes The same UNTIL objects before initialization, after initialization and after reset
     */
    #[DataProvider('providerHistories')]
    public function testTheSameConditionsObserveRepositoryTransitionsWithoutInputSql(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $profile = $session->semantics()->profile();
                $outcomes = array_map(static function (M\Statement $node) use ($native, $session, $profile): array {
                    $sql = (new Reference())->sql($node, $profile);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque non-SQL request label';
                    $actual = (new Memory())->observe($session, $node);
                    self::assertSame(serialize($expected), serialize($actual), $sql);
                    /** Let a native applier publish its reached UNTIL stop before the next request. */
                    usleep(50000);
                    return $actual;
                }, $nodes);
                self::assertSame([1871, null, 1871], [$outcomes[2]['error'][0] ?? null, $outcomes[4]['error'][0] ?? null, $outcomes[7]['error'][0] ?? null]);
                self::assertSame('opaque non-SQL request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
