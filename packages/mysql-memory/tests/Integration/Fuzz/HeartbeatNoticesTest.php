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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class HeartbeatNoticesTest extends TestCase
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
    public function testSameStructureResolvesNoticesAfterTimeoutChanges(string $release): void
    {
        $legacy = str_starts_with($release, '5.');
        $terminology = $legacy ? Terminology::Legacy : Terminology::Current;
        $node = new ChangeReplicationSource($terminology, [new SourceOption($terminology, SourceOptionKind::HeartbeatPeriod, new NumberLiteral('100'))]);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $name = $legacy ? 'slave_net_timeout' : 'replica_net_timeout';
                $observed = array_map(static function (int $timeout) use ($native, $session, $node, $sql, $name): array {
                    $setting = 'SET GLOBAL ' . $name . '=' . $timeout;
                    $native->query($setting);
                    $session->query($setting);
                    $expected = (new Native())->observe($native, $sql);
                    self::assertIsArray($expected['warnings']);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);

                    return [serialize($expected), serialize($actual), array_column($expected['warnings'], 1)];
                }, [200, 10, 200]);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([false, true, false], array_map(static fn (array $row): bool => in_array('1704', $row[2], true), $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
