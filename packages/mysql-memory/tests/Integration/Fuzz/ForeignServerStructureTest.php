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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ForeignServerStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<list<Statement>>}>
     */
    public static function providerStructures(): iterable
    {
        $histories = [];
        foreach ([ServerOptionKind::Host, ServerOptionKind::Database, ServerOptionKind::User, ServerOptionKind::Password, ServerOptionKind::Socket, ServerOptionKind::Owner] as $kind) {
            foreach (['', 'é', '猫', '😀', 'abc😀def', "a\0b", 'a  ', str_repeat('x', 63) . '猫', str_repeat('x', 255) . '猫'] as $value) {
                $create = new CreateServer(new Name('s'), new Name($value), [new ServerOption($kind, new Text($value))]);
                $alter = new AlterServer(new Name('s'), [new ServerOption($kind, new Text($value))]);
                $drop = new DropServer(false, new Name('s'));
                $histories[] = [$alter, $create, $create, $alter, $drop, $alter, $create, $drop];
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $histories];
        }
    }

    /**
     * @param list<list<Statement>> $histories The same public objects reused across absence, creation, alteration and removal
     */
    #[DataProvider('providerStructures')]
    public function testDirectStructuresRetainTextAndConversionAcrossStateTransitions(string $release, array $histories): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
            $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
            $results = array_map(static function (array $history) use ($native, $session): array {
                return array_map(static function (Statement $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual)];
                }, $history);
            }, $histories);
            $results = array_merge(...$results);

            self::assertSame(array_column($results, 0), array_column($results, 1));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
