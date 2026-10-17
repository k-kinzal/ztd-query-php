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
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class IndexParserCatalogStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Table\CreateTable>, list<int|null>}>
     */
    public static function providerStructures(): iterable
    {
        $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Text)));
        $index = new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText, [new \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart(new Name('a'))], options: [new \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser(new Name('ngram'))]);
        $statement = new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t'), new Name('plugin_domain')), [$column, $index]);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [$statement], $release === '5.6.51' ? [1128, 1128, 1128] : [1049, null, 1049]];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\CreateTable> $statements The same structures before table creation, after creation and after removal
     * @param list<int|null> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameDefinitionResolvesItsPluginBeforeItsDatabase(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE DATABASE plugin_domain', 'DROP DATABASE plugin_domain']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): ?int => $state[0][2], $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
