<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(SelectSql::class)]
#[Small]
final class SelectSqlTest extends TestCase
{
    public function testRenderAddsOutputAliases(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('"'))]);
        self::assertSame('SELECT 2 AS """"', (new SelectSql())->render($select));
    }

    public function testLocalPrefixDeclaresTheSlotNamesBeforeTheAnchor(): void
    {
        $source = new LocalSource(new ValuesQuery([new ValueRow([new IntegerLiteral('2')])]), [new Identifier('n')]);

        self::assertSame('WITH "__ztd_cte" ("n") AS (VALUES (2)) ', (new SelectSql())->localPrefix($source));
    }

    public function testOrderingRetainsPositionDirectionAndNullPlacement(): void
    {
        $renderer = new SelectSql();

        self::assertSame(' ORDER BY 2 DESC NULLS FIRST', $renderer->ordering([new Order(2, true, true)]));
    }

    public function testLimitPreservesTheFirstSpellingOfASharedSlot(): void
    {
        $limit = new Limit(new Parameter('1'), new Parameter('01'), true);

        self::assertSame('LIMIT ?01, ?1', (new SelectSql())->limit($limit));
    }

    public function testExpressionKeepsNegativeValues(): void
    {
        self::assertSame('-2', (new SelectSql())->expression(new IntegerLiteral('-2'), null));
    }

    public function testSourceQualifiesTheExternalNameAndItsAlias(): void
    {
        $source = new TableSource(new Identifier('main'), new Identifier('metrics'), []);

        self::assertSame('"main"."metrics" AS "__ztd_source_2"', (new SelectSql())->source($source, 2));
    }

    public function testAliasDistinguishesTheFirstAndLaterOccurrences(): void
    {
        $sql = new SelectSql();
        self::assertSame('"__ztd_source"', $sql->alias(0));
        self::assertSame('"__ztd_source_1"', $sql->alias(1));
    }

    public function testChoiceWritesTheBaseAndBranchesInOrder(): void
    {
        $one = new IntegerLiteral('1');
        $two = new IntegerLiteral('2');
        $choice = new CaseExpression($one, [new CaseBranch($one, $two), new CaseBranch($two, $one)], $two);

        self::assertSame('(CASE 1 WHEN 1 THEN 2 WHEN 2 THEN 1 ELSE 2 END)', (new SelectSql())->choice($choice, null, []));
    }

    public function testBetweenRendersEveryRoleOnceInOrder(): void
    {
        $range = new BetweenTest(new Parameter('02'), new Parameter('3'), new Parameter('1'), true);

        self::assertSame('(?02 NOT BETWEEN ?3 AND ?1)', (new SelectSql())->between($range, null, []));
    }

    public function testPatternPreservesTheOperandAndEscapeOrder(): void
    {
        $test = new PatternTest(PatternKind::AsciiLike, new Parameter('02'), new Parameter('3'), true, new Parameter('1'));

        self::assertSame('(?02 NOT LIKE ?3 ESCAPE ?1)', (new SelectSql())->pattern($test, null, []));
    }

    public function testTruthUsesTheNumberedOperandOnce(): void
    {
        $test = new TruthTest(new Parameter('02'), false, true);

        self::assertSame('(CASE WHEN (NOT ?02) THEN 0 ELSE 1 END)', (new SelectSql())->truth($test, null, []));
    }

    public function testMembershipPreservesOrderAndTheEmptyOperand(): void
    {
        $operand = new Parameter('02');
        $one = new MembershipItem(new Parameter('1'));
        $three = new MembershipItem(new Parameter('3'));
        $renderer = new SelectSql();

        self::assertSame('(?02 NOT IN (?3, ?1))', $renderer->membership(new MembershipTest($operand, [$three, $one], true), null, []));
        self::assertSame('(?02 IN ())', $renderer->membership(new MembershipTest($operand, []), null, []));
    }

    public function testFoldedMembershipWritesTheDecidedValueAndTheSurvivingBindings(): void
    {
        $renderer = new SelectSql();
        $value = new FoldedMembership(true, [new Parameter('02'), new Parameter('2'), new Parameter('7')]);

        self::assertSame('((CASE WHEN ?02 THEN 0 WHEN ?2 THEN 0 WHEN ?7 THEN 0 END) NOT IN ())', $renderer->foldedMembership($value));
        self::assertSame('0', $renderer->foldedMembership(new FoldedMembership(false)));
    }

    public function testFoldedMembershipRetainsItsNativeIndexShapeWithoutBindings(): void
    {
        $renderer = new SelectSql(preserveEmptyMembership: true);

        self::assertSame('(0 NOT IN ())', $renderer->foldedMembership(new FoldedMembership(true)));
        self::assertSame('(0 IN ())', $renderer->foldedMembership(new FoldedMembership(false)));
    }

    public function testColumnKeepsTheCheckedOccurrenceAndSlot(): void
    {
        $from = new TableSource(new Identifier('main'), new Identifier('accounts'), [new Identifier('id'), new Identifier('tenant')]);
        $column = new ColumnReference(1);

        self::assertSame('"__ztd_source"."tenant"', (new SelectSql())->column($column, $from, []));
    }

    public function testRenderAddsTheDisambiguatingPredicateOnlyWhenRequested(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('first'))]);
        $renderer = new SelectSql();

        self::assertSame('SELECT 1 AS "first"', $renderer->render($select));
        self::assertSame('SELECT 1 AS "first" WHERE 1', $renderer->render($select, requireWhere: true));
    }

    public function testCoalesceEmitsARegisteredMarkerAfterTheNullOperand(): void
    {
        $call = new Coalesce([new CoalesceArgument(new NullLiteral()), new CoalesceArgument(new Parameter('007'))]);

        self::assertSame('COALESCE(NULL, ?007)', (new SelectSql())->coalesce($call, null, []));
    }
}
