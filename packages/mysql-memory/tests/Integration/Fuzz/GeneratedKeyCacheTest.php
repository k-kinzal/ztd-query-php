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
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedKeyCacheTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Server\KeyCache\CacheIndex}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStatements(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('row-roles-20261017:141');
        $choices->one($roots);
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[141], 6);
        Check::invariant($node instanceof M\Server\KeyCache\CacheIndex, 'The recorded whole-model root retains its key cache identity.');
        $initial = substr((new Profiles())->select($node)->grammar->value, 6);
        foreach (array_unique([$initial, '8.4.7', '9.1.0']) as $release) {
            yield $release => [$release, $node];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTheSameRequestSeesPopulatedTablesAndTheCacheAllocationHistory(string $release, M\Server\KeyCache\CacheIndex $node): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->query('USE d');
                $session = (new Instance($release, [], ['d']))->connect();
                $session->query('USE d');
                $reference = new Reference();
                $setup = array_merge(...array_map(static fn (M\Server\KeyCache\CachedTable $table): array => (new \Fuzz\Structure\Audit\Table\Changes())->table($table->table, false), $node->tables));
                array_map(static function (M\Statement $step) use ($native, $session, $reference): void {
                    self::assertSame((new Native())->observe($native, $reference->sql($step, $session->semantics()->profile())), (new Memory())->observe($session, $step));
                }, $setup);
                $history = array_map(static function (M\Statement $step) use ($native, $session, $reference, $node): array {
                    self::assertSame((new Native())->observe($native, $reference->sql($step, $session->semantics()->profile())), (new Memory())->observe($session, $step));
                    $expected = (new Native())->observe($native, $reference->sql($node, $session->semantics()->profile()));
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $node));
                    return $expected;
                }, [new M\Utility\Set\SetVariables([new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('unrelated')), new M\Literal\NumberLiteral('0'))]), ...(new \Fuzz\Structure\Audit\Storage\History())->steps($node, 1284), new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('key_buffer_size'), new M\Literal\NumberLiteral('0'), M\Variable\VariableScope::Global, $node->cache)])]);
                self::assertSame([1284, null, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                array_map(static function (M\Server\KeyCache\CachedTable $table) use ($native, $session, $reference): void {
                    $read = new M\QueryRoot(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference($table->table)));
                    $expected = (new Native())->observe($native, $reference->sql($read, $session->semantics()->profile()));
                    self::assertSame($expected, (new Memory())->observe($session, $read));
                    self::assertSame([['7']], $expected['results'][0]['rows']);
                }, $node->tables);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
