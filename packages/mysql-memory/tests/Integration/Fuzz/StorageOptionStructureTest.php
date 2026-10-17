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
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StorageOptionStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<list<Statement>>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $histories = [];
            foreach ([0, 1, 65535] as $group) {
                foreach (['InnoDB', 'ndb'] as $engine) {
                    $options = [new EngineOption(new Name($engine)), new NodegroupOption(new Numeral('65535')), new NodegroupOption(new Numeral((string) $group)), new SizeOption(SizeOptionKind::Initial, new ByteSize(null, new Name('4M')))];
                    $create = new CreateTablespace(new Name('ts'), new Text('ts.ibd'), options: $options);
                    $drop = new DropTablespace(new Name('ts'));
                    $histories[] = [$drop, $create, $create, $drop, $drop, $create, $drop];
                }
            }
            yield $release => [$release, $histories];
        }
    }

    /**
     * @param list<list<Statement>> $histories Accepted constructor values reused before and after definition changes
     */
    #[DataProvider('providerHistories')]
    public function testSameOptionsExecuteAcrossTablespaceStateTransitions(string $release, array $histories): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $differences = array_map(static function (array $history) use ($native, $session): array {
                return array_filter(array_map(static function (Statement $node) use ($native, $session): ?array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return serialize($expected) === serialize($actual) ? null : [$sql, $expected, $actual];
                }, $history));
            }, $histories);

            self::assertSame([], array_merge(...$differences));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
