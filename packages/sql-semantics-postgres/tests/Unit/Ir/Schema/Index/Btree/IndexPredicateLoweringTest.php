<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Facade\StatementBinder;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex as ParsedCreateIndex;

#[CoversClass(IndexPredicateLowering::class)]
#[Medium]
final class IndexPredicateLoweringTest extends TestCase
{
    public function testLowerDischargesQualificationIntoTheSameIndexedSlot(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context(['CREATE TABLE public.orders (id integer, active boolean)']);
        $bare = $analyzer->analyze('CREATE INDEX selected ON public.orders (id) WHERE active IS NULL', $context);
        $qualified = $analyzer->analyze('CREATE INDEX selected ON orders (id) WHERE ((public.orders.active) IS NULL)', $context);

        self::assertInstanceOf(CreateIndex::class, $bare);
        self::assertInstanceOf(CreateIndex::class, $qualified);
        self::assertEquals($bare->predicate, $qualified->predicate);
        self::assertEquals($bare, $analyzer->analyze($qualified->toString(), $context));
        self::assertInstanceOf(NullIndexPredicate::class, $bare->predicate);
        self::assertSame(1, $bare->predicate->slot);
        self::assertSame('CREATE INDEX "selected" ON "public"."orders" USING btree ("id" ASC NULLS LAST) WHERE ("active" IS NULL)', $qualified->toString());
    }

    public function testNodeKeepsTheBoundOrderedTree(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context(['CREATE TABLE public.orders (id integer, active boolean, ref bigint)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $sql = 'CREATE INDEX selected ON orders (id) WHERE NOT (public.orders.active IS NULL OR (orders.ref IS NOT NULL AND id IS NULL))';
        $operation = (new StatementBinder(Platform::shared()->parser($analyzer->profile())))->resolve($sql, $context);
        self::assertInstanceOf(ParsedCreateIndex::class, $operation->statement);
        self::assertNotNull($operation->statement->where);

        $expected = new IndexBooleanPredicate(
            LogicalOperator::Not,
            new IndexBooleanPredicate(
                LogicalOperator::Or,
                new NullIndexPredicate(1),
                new IndexBooleanPredicate(LogicalOperator::And, new NullIndexPredicate(2, true), new NullIndexPredicate(0))
            ),
        );
        self::assertEquals($expected, (new IndexPredicateLowering())->node($operation, $operation->statement, $operation->statement->where));
        $index = $analyzer->analyze($sql, $context);
        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertEquals($expected, $index->predicate);
        self::assertEquals($index, $analyzer->analyze($index->toString(), $context));
    }

    public function testSlotBindsDifferentNativeHeadingPositions(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context(['CREATE TABLE public.orders (id integer, active boolean, ref bigint)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $operation = (new StatementBinder(Platform::shared()->parser($analyzer->profile())))->resolve(
            'CREATE INDEX selected ON orders (id) WHERE ref IS NOT NULL',
            $context,
        );

        self::assertInstanceOf(ParsedCreateIndex::class, $operation->statement);
        self::assertInstanceOf(NullTest::class, $operation->statement->where);
        self::assertSame(2, (new IndexPredicateLowering())->slot($operation, $operation->statement, $operation->statement->where->operand));
    }

    public function testLowerPreservesTruthOperationAndQualifiedColumn(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context(['CREATE TABLE public.orders (id integer, active boolean, delivered boolean)']);
        $index = $analyzer->analyze(
            'CREATE INDEX selected ON orders (id) WHERE orders.active IS UNKNOWN AND public.orders.delivered IS NOT TRUE',
            $context,
        );

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertEquals(
            new IndexBooleanPredicate(LogicalOperator::And, new IndexTruthTest(1, TruthOperator::IsUnknown), new IndexTruthTest(2, TruthOperator::IsNotTrue)),
            $index->predicate,
        );
        self::assertEquals($index, $analyzer->analyze($index->toString(), $context));
    }
}
