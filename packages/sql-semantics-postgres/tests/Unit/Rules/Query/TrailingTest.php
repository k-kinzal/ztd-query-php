<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Trailing;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Trailing::class)]
#[Medium]
final class TrailingTest extends TestCase
{
    public function testQueryFollowsTheRightOperandOfASetOperation(): void
    {
        $union = (new Inspection())->diagnose('SELECT 1 UNION SELECT a AS x FROM t WHERE b')->statement;
        self::assertInstanceOf(Query::class, $union);
        self::assertEquals(new ColumnReference([new Name('b')]), (new Trailing())->query($union));
    }

    public function testQueryFollowsTheClausesAfterTheBody(): void
    {
        $ordered = (new Inspection())->diagnose('WITH w AS (SELECT 1) SELECT 1 UNION SELECT 2 ORDER BY c')->statement;
        self::assertInstanceOf(Query::class, $ordered);
        self::assertEquals(new ColumnReference([new Name('c')]), (new Trailing())->query($ordered));
    }

    public function testQueryIsNullForValues(): void
    {
        $values = (new Inspection())->diagnose('VALUES (a)')->statement;
        self::assertInstanceOf(Query::class, $values);
        self::assertNull((new Trailing())->query($values));
    }

    public function testTakesWithFindsAnOpenIsJsonTestAtTheEnd(): void
    {
        $semantics = new Inspection();
        $open = $semantics->diagnose('SELECT a FROM t WHERE b IS JSON')->statement;
        $closed = $semantics->diagnose('SELECT a FROM t WHERE b IS JSON WITH UNIQUE KEYS')->statement;
        $aliased = $semantics->diagnose('SELECT b IS JSON AS j')->statement;
        self::assertInstanceOf(Query::class, $open);
        self::assertInstanceOf(Query::class, $closed);
        self::assertInstanceOf(Query::class, $aliased);
        self::assertSame([true, false, false], [(new Trailing())->takesWith($open), (new Trailing())->takesWith($closed), (new Trailing())->takesWith($aliased)]);
    }

    public function testSelectAnswersTheLastUnaliasedTarget(): void
    {
        $select = (new Inspection())->diagnose('SELECT a, b')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertEquals(new ColumnReference([new Name('b')]), (new Trailing())->select($select));
    }

    public function testSelectAnswersHavingAfterGroupBy(): void
    {
        $select = (new Inspection())->diagnose('SELECT a FROM t GROUP BY a, c HAVING d')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertEquals(new ColumnReference([new Name('d')]), (new Trailing())->select($select));
    }

    public function testSelectAnswersTheLastGroupingExpression(): void
    {
        $select = (new Inspection())->diagnose('SELECT a FROM t GROUP BY a, c')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertEquals(new ColumnReference([new Name('c')]), (new Trailing())->select($select));
    }

    public function testSelectIsNullAfterIntoWindowOrAStar(): void
    {
        $semantics = new Inspection();
        $into = $semantics->diagnose('SELECT a INTO n')->statement;
        $window = $semantics->diagnose('SELECT a FROM t WINDOW w AS ()')->statement;
        $star = $semantics->diagnose('SELECT *')->statement;
        self::assertInstanceOf(Select::class, $into);
        self::assertInstanceOf(Select::class, $window);
        self::assertInstanceOf(Select::class, $star);
        self::assertSame([null, null, null], [(new Trailing())->select($into), (new Trailing())->select($window), (new Trailing())->select($star)]);
    }

    public function testOptionsAnswersTheLastSortExpressionWithoutDirection(): void
    {
        $semantics = new Inspection();
        $plain = $semantics->diagnose('SELECT 1 ORDER BY a, b')->statement;
        $descending = $semantics->diagnose('SELECT 1 ORDER BY a, b DESC')->statement;
        self::assertInstanceOf(Select::class, $plain);
        self::assertInstanceOf(Select::class, $descending);
        self::assertInstanceOf(SelectOptions::class, $plain->options);
        self::assertInstanceOf(SelectOptions::class, $descending->options);
        self::assertEquals([new ColumnReference([new Name('b')]), null], [(new Trailing())->options($plain->options), (new Trailing())->options($descending->options)]);
    }

    public function testOptionsAnswersTheLimitOnlyBeforeTheLocking(): void
    {
        $semantics = new Inspection();
        $after = $semantics->diagnose('SELECT 1 LIMIT c FOR UPDATE')->statement;
        $before = $semantics->diagnose('SELECT 1 FOR UPDATE LIMIT c')->statement;
        self::assertInstanceOf(Select::class, $after);
        self::assertInstanceOf(Select::class, $before);
        self::assertInstanceOf(SelectOptions::class, $after->options);
        self::assertInstanceOf(SelectOptions::class, $before->options);
        self::assertEquals([null, new ColumnReference([new Name('c')])], [(new Trailing())->options($after->options), (new Trailing())->options($before->options)]);
    }

    public function testLimitAnswersTheCountWrittenLast(): void
    {
        $semantics = new Inspection();
        $offsetLast = $semantics->diagnose('SELECT 1 LIMIT a OFFSET b')->statement;
        $limitLast = $semantics->diagnose('SELECT 1 OFFSET b LIMIT a')->statement;
        $fetch = $semantics->diagnose('SELECT 1 OFFSET b FETCH FIRST 1 ROW ONLY')->statement;
        self::assertInstanceOf(Select::class, $offsetLast);
        self::assertInstanceOf(Select::class, $limitLast);
        self::assertInstanceOf(Select::class, $fetch);
        self::assertInstanceOf(RowLimit::class, $offsetLast->options?->limit);
        self::assertInstanceOf(RowLimit::class, $limitLast->options?->limit);
        self::assertInstanceOf(RowLimit::class, $fetch->options?->limit);
        self::assertEquals(
            [new ColumnReference([new Name('b')]), new ColumnReference([new Name('a')]), null],
            [(new Trailing())->limit($offsetLast->options->limit), (new Trailing())->limit($limitLast->options->limit), (new Trailing())->limit($fetch->options->limit)],
        );
    }

    public function testRelationAnswersTheLastJoinCondition(): void
    {
        $semantics = new Inspection();
        $list = $semantics->diagnose('SELECT 1 FROM v, t CROSS JOIN u JOIN w ON b')->statement;
        $using = $semantics->diagnose('SELECT 1 FROM t JOIN u USING (a)')->statement;
        self::assertInstanceOf(Select::class, $list);
        self::assertInstanceOf(Select::class, $using);
        self::assertInstanceOf(Relation::class, $list->from);
        self::assertInstanceOf(Relation::class, $using->from);
        self::assertEquals([new ColumnReference([new Name('b')]), null], [(new Trailing())->relation($list->from), (new Trailing())->relation($using->from)]);
    }
}
