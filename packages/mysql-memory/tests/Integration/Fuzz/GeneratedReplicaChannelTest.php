<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedReplicaChannelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Statement>, int}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerHistories(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([110, 111] as $index) {
            $choices = new Choices('row-roles-20261017:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            Check::invariant($node instanceof M\Replication\Replica\StartReplica || $node instanceof M\Replication\Replica\StopReplica, 'The recorded whole-model roots retain their replica command identities.');
            $terms = $node->terminology;
            $setup = new M\Replication\Source\ChangeReplicationSource($terms, [new M\Replication\Source\SourceOption($terms, M\Replication\Source\SourceOptionKind::Host, new M\Literal\Text('127.0.0.1')), new M\Replication\Source\SourceOption($terms, M\Replication\Source\SourceOptionKind::Port, new M\Literal\Numeral('1')), new M\Replication\Source\SourceOption($terms, M\Replication\Source\SourceOptionKind::User, new M\Literal\Text('replication_fixture'))], channel: $node->channel);
            $stop = new M\Replication\Replica\StopReplica($terms, channel: $node->channel);
            $reset = new M\Replication\Reset\Reset([new M\Replication\Reset\ResetReplica($terms, true, $node->channel)]);
            foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $index => [$release, [$node, $setup, $node, $stop, $reset, $node], $index === 110 ? 1200 : 3074];
            }
        }
    }

    /**
     * @param list<M\Statement> $steps The original command before, during and after channel configuration
     */
    #[DataProvider('providerHistories')]
    public function testUnchangedReplicaCommandsFollowTheirChannelLifetime(string $release, array $steps, int $absentCode): void
    {
        self::assertSame($steps[0], $steps[2]);
        self::assertSame($steps[0], $steps[5]);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $history = array_map(static function (M\Statement $node) use ($native, $session): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque request label';
                    $actual = (new Memory())->observe($session, $node);
                    self::assertSame($expected, $actual, $sql);
                    return $expected;
                }, $steps);
                self::assertSame([$absentCode, null, null, null, null, $absentCode], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
