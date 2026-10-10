<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\AggregateOwnership;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(AggregateOwnership::class)]
#[Medium]
final class AggregateOwnershipTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerRegisterAssignsEachOccurrenceToItsResolvedBlock(): iterable
    {
        yield 'outer column' => ['SELECT (SELECT SUM(t.a)) FROM t', 1, 0];
        yield 'outer group concat' => ['SELECT (SELECT GROUP_CONCAT(t.a ORDER BY t.a)) FROM t', 1, 0];
        yield 'outer json' => ['SELECT (SELECT JSON_OBJECTAGG(t.a,t.a)) FROM t', 1, 0];
        yield 'local and outer columns' => ['SELECT (SELECT SUM(t.a+u.a) FROM u) FROM t', 0, 1];
        yield 'constant argument' => ['SELECT (SELECT SUM(1)) FROM t', 0, 1];
        yield 'star' => ['SELECT (SELECT COUNT(*)) FROM t', 0, 1];
        yield 'window' => ['SELECT (SELECT SUM(t.a) OVER ()) FROM t', 0, 0];
        yield 'reduced scalar argument' => ['SELECT (SELECT SUM((SELECT t.a))) FROM t', 1, 0];
    }

    #[DataProvider('providerRegisterAssignsEachOccurrenceToItsResolvedBlock')]
    public function testRegisterAssignsEachOccurrenceToItsResolvedBlock(string $sql, int $outer, int $inner): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [$semantics->analyze('CREATE TABLE t(a INT)'), $semantics->analyze('CREATE TABLE u(a INT)')];
        $operation = $semantics->analyze($sql, $tables);
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        self::assertInstanceOf(Select::class, $subquery->query);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertCount($outer, $operation->facts->query($operation->statement)->aggregates);
        self::assertCount($inner, $operation->facts->query($subquery->query)->aggregates);
    }

    public function testReferencesNamesTheBoundOccurrencesWithoutEnteringUnreducedQueries(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze('SELECT SUM(t.a+(SELECT MAX(a) FROM t)) FROM t', [$table]);
        $call = $operation->field(0)->expression;
        self::assertInstanceOf(SetFunction::class, $call);

        self::assertSame([$operation->inputRelation()], (new AggregateOwnership())->references($call, $operation->facts));
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([$call], $operation->facts->query($operation->statement)->aggregates);
    }
}
