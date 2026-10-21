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
use MySqlMemory\Typing\Domain;
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
final class GeneratedKillTargetTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Server\Instance\Kill}>
     * @throws JsonException When the reviewed inventory cannot be read
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStatements(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('row-roles-20261017:138');
        $choices->one($roots);
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[138], 6);
        Check::invariant($node instanceof M\Server\Instance\Kill && $node->scope !== M\Server\Instance\KillScope::Query, 'The generated request retains its connection-ending role.');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $node];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTheSameRequestAndBindingSeeAConnectedThenClosedTarget(string $release, M\Server\Instance\Kill $node): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $target = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $instance = new Instance($release);
                $session = $instance->connect(threadId: $native->thread_id);
                $memoryTarget = $instance->connect(threadId: $target->thread_id);
                $session->text = 'opaque request label';
                $sql = (new Reference())->sql($node, $session->semantics()->profile(), '?');
                $initial = (new Native())->observe($native, $sql, bindings: [null]);
                self::assertSame($initial, (new Memory())->observe($session, $node));
                self::assertSame(1094, $initial['error'][0] ?? null);
                $history = array_map(static function () use ($native, $target, $session, $node, $sql): array {
                    $expected = (new Native())->observe($native, $sql, bindings: [$target->thread_id]);
                    self::assertSame($expected, (new Memory())->observe($session, $node, [[$target->thread_id, Domain::integer()]]));
                    return $expected;
                }, [0, 1]);
                self::assertSame([null, 1094], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertTrue($memoryTarget->released);
                self::assertSame('opaque request label', $session->text);
                self::assertSame($sql, (new Reference())->sql($node, $session->semantics()->profile(), '?'));
            } finally {
                $target->close();
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
