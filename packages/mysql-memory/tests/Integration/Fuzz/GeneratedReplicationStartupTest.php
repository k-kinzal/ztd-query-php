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
use Fuzz\Structure\Profiles;
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
final class GeneratedReplicationStartupTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStartup(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([104, 114, 204] as $index) {
            $choices = new Choices('row-roles-20261017:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            Check::invariant($node instanceof M\Replication\Filter\ChangeReplicationFilter || $node instanceof M\Replication\Source\ChangeReplicationSource || $node instanceof M\Utility\Show\Replication\ShowRelaylogEvents, 'The recorded whole-model roots retain their replication identities.');
            $release = substr((new Profiles())->select($node)->grammar->value, 6);
            yield $release . ':' . $index => [$release, $node];
            if ($node instanceof M\Utility\Show\Replication\ShowRelaylogEvents) {
                yield '5.7.44:' . $index => ['5.7.44', $node];
            }
        }
    }

    #[DataProvider('providerStartup')]
    public function testTheUnchangedGeneratedStructureDependsOnStartupAvailability(string $release, M\Statement $node): void
    {
        $before = get_object_vars($node);
        $history = array_map(static function (int $initial) use ($release, $node): array {
            $container = Testcontainers::run((new DisposableMySql($release))->withCommands(['mysqld', '--server-id=' . $initial]));
            try {
                $endpoint = $container->getData(Endpoint::class);
                $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
                try {
                    $session = (new Instance($release, ['server_id' => $initial]))->connect();
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque request label';
                    $actual = (new Memory())->observe($session, $node);
                    self::assertSame($expected, $actual, $sql);
                    self::assertSame('opaque request label', $session->text);
                    return $expected;
                } finally {
                    $native->close();
                }
            } finally {
                $container->stop();
            }
        }, [0, 1, 0]);
        self::assertSame([1794, null, 1794], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
        self::assertSame($before, get_object_vars($node));
    }
}
