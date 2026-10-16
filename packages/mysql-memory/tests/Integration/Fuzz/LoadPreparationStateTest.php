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
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LoadPreparationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<LoadTable>, list<int>}>
     */
    public static function providerStructures(): iterable
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('source_table'))));
        $comparison = new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::All, $query);
        $statement = new LoadTable(LoadFormat::Data, null, new LoadInput(false, LoadSource::Infile, new Text('text')), null, new WriteTarget(new QualifiedName(new Name('t'))), assignments: [new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new ColumnUse(new Name('a')), $comparison)]);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [$statement], [1146, 1290, 1241, 1146]];
        }
    }

    /**
     * @param list<LoadTable> $statements The same structures before table creation, after creation and after removal
     * @param list<int> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameSemanticSubqueryTracksItsCatalogWidth(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE load_domain');
                $native->select_db('load_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $native->query('CREATE TABLE t(a INT)');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE load_domain; USE load_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci; CREATE TABLE t(a INT)');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (LoadTable $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE TABLE source_table(a INT)', 'ALTER TABLE source_table ADD b INT', 'DROP TABLE source_table']);
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
