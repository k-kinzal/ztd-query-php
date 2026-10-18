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
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RecursiveStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\QueryRoot}> A complete structure built without parsing
     */
    public static function providerStructures(): iterable
    {
        $anchor = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], new M\Relation\TableReference(new QualifiedName(new Name('t'))));
        $step = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Name\ColumnUse(new Name('n')), new M\Literal\NumberLiteral('1'));
        $bound = new M\Expression\Comparison(M\Expression\ComparisonOperator::Less, new M\Name\ColumnUse(new Name('n')), new M\Literal\NumberLiteral('9'));
        $recursive = new M\Query\Select([], [new M\Query\SelectExpression($step)], new M\Relation\TableReference(new QualifiedName(new Name('c'))), where: $bound);
        $definition = new M\Query\With\CommonTableExpression(new Name('c'), [new Name('n')], new M\Query\Set\SetOperation($anchor, M\Query\Set\SetOperator::Union, M\Query\Set\SetQuantifier::All, $recursive));
        $root = new M\QueryRoot(new M\Query\QueryExpression(new M\Query\With\With(true, [$definition]), new M\Query\ExplicitTable(new QualifiedName(new Name('c')))));
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $root];
        }
    }

    #[DataProvider('providerStructures')]
    public function testSameRecursiveReadUsesCurrentAnchorRowsWithoutSourceSql(string $release, M\QueryRoot $root): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $sql = (new Reference())->sql($root, $session->semantics()->profile());
            $history = array_map(static function (array $setup) use ($native, $session, $sql, $root): array {
                foreach ($setup as $step) {
                    $native->query($step);
                    $session->query($step);
                }
                $session->text = 'unrelated request label';
                return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $root)];
            }, [[], ['CREATE DATABASE d', 'USE d'], ['CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(7)'], ['DELETE FROM t'], ['DROP TABLE t']]);
            self::assertSame([1046, 1146, null, null, 1146], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
            self::assertSame([['7'], ['8'], ['9']], $history[2][0]['results'][0]['rows']);
            self::assertSame(array_map(serialize(...), array_column($history, 0)), array_map(serialize(...), array_column($history, 1)));
            self::assertSame('unrelated request label', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
