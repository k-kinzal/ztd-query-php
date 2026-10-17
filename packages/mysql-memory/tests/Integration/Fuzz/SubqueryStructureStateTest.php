<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SubqueryStructureStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<QueryRoot>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('subquery-structure-state-20261018');
        $operators = [ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::Multiply];
        $structures = [];
        for ($trial = 0; $trial < 15; $trial++) {
            $value = new Arithmetic($operators[$trial % count($operators)], new ColumnUse(new Name('v')), new NumberLiteral((string) $choices->integer(1, 100)));
            $query = new Select([], [new SelectExpression($value)], new TableReference(new QualifiedName(new Name('t'))));
            $structures[] = new QueryRoot(new Select([], [new SelectExpression(new ScalarSubquery($query), new Name('answer'))]));
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<QueryRoot> $structures Directly constructed scalar reads, reused through every history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameScalarSubqueriesFollowCatalogAndCardinalityChanges(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release))->connect();
            $session->query('CREATE DATABASE d; USE d');
            $observations = array_map(static function (QueryRoot $query) use ($native, $session): array {
                $sql = (new Reference())->sql($query, $session->semantics()->profile());
                $transitions = ['SET @run=0', 'CREATE TABLE t(v INT, other INT)', 'INSERT INTO t VALUES(7,0)', 'INSERT INTO t VALUES(8,0)', 'DELETE FROM t WHERE v=8', 'ALTER TABLE t DROP COLUMN v', 'DROP TABLE t'];
                $history = array_map(static function (string $setup) use ($session, $native, $query, $sql): array {
                    $native->query($setup);
                    $session->query($setup);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $query)];
                }, $transitions);
                self::assertSame([1146, null, null, 1242, null, 1054, 1146], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                self::assertSame('unrelated invalid input', $session->text);
                return $history;
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
