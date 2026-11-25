<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SimpleCaseArm;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\Order;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(SelectSql::class)]
#[Small]
final class SelectSqlTest extends TestCase
{
    public function testIntegerMembershipEmitsTheExactSignedAlternativeList(): void
    {
        $test = new IntegerMembership(new RangeIntegerLiteral('2147483648'), [new NullLiteral(), new RangeIntegerLiteral('2', 2)], true);

        self::assertSame('(2147483648 NOT IN (NULL, 002))', (new SelectSql())->integerMembership($test, null, 0, true));
    }

    public function testIntegerRangeEmitsTheSignedOrderedPredicate(): void
    {
        $range = new IntegerRange(new RangeIntegerLiteral('2'), new RangeIntegerLiteral('3'), new NullLiteral(), true);

        self::assertSame('(2 NOT BETWEEN 3 AND NULL)', (new SelectSql())->integerRange($range, null));
    }

    public function testRangeOperandWritesTheFullSignedMinimum(): void
    {
        $operand = new RangeIntegerLiteral('-9223372036854775808');

        self::assertSame('-9223372036854775808', (new SelectSql())->rangeOperand($operand, null, 0, true));
    }

    public function testRenderAddsOutputAliases(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('`'))]);
        self::assertSame('SELECT 2 AS ````', (new SelectSql())->render($select));
    }

    public function testRenderUsesAnIndependentRowForSourceFreeFiltersOnMySql5(): void
    {
        $select = new Select(
            [new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))],
            release: GrammarRelease::MySql5651,
            where: new BooleanLiteral(false),
        );

        self::assertSame('SELECT 1 AS `a` FROM DUAL WHERE FALSE', (new SelectSql())->render($select));
    }

    public function testRenderPreservesSourceFreeOrderingOnMySql56(): void
    {
        $select = new Select(
            [new ResultColumn(new IntegerLiteral('17'), new Identifier('n'))],
            release: GrammarRelease::MySql5651,
            orderBy: [new Order(1, true)],
        );

        self::assertSame(
            'SELECT 17 AS `n` FROM (SELECT 1 AS `__ztd_unit`) AS `__ztd_single` ORDER BY 1 DESC',
            (new SelectSql())->render($select)
        );
    }

    public function testExpressionKeepsNegativeValues(): void
    {
        self::assertSame('-2', (new SelectSql())->expression(new IntegerLiteral('-2'), null));
    }

    public function testAliasSeparatesJoinedInputOrdinals(): void
    {
        $sql = new SelectSql();

        self::assertSame('`__ztd_source_1`', $sql->alias(1));
    }

    public function testFromKeepsTheJoinAssociation(): void
    {
        $a = new TableSource(new Identifier('ir_fixture'), new Identifier('a'), []);
        $b = new TableSource(new Identifier('ir_fixture'), new Identifier('b'), []);
        $joined = new Join(JoinKind::Cross, $a, $b);

        self::assertSame('(`ir_fixture`.`a` AS `__ztd_source` CROSS JOIN `ir_fixture`.`b` AS `__ztd_source_1`)', (new SelectSql())->from($joined));
    }

    public function testPredicateEmitsNullInItsOwnRole(): void
    {
        self::assertSame('NULL', (new SelectSql())->predicate(new NullLiteral(), null));
    }
    public function testLocalColumnNamesTheResultOrdinal(): void
    {
        self::assertSame('`__ztd_c3`', (new SelectSql())->localColumn(3));
    }

    public function testLocalColumnListNamesEachExportedOrdinal(): void
    {
        self::assertSame(' (`__ztd_c0`, `__ztd_c1`)', (new SelectSql())->localColumnList(2));
    }

    public function testColumnUsesTheDeclaredVarcharFieldAndOccurrenceAlias(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('items'), [new Identifier('label')], [new Varchar(8)]);

        self::assertSame('`__ztd_source_1`.`label`', (new SelectSql())->column(new TextColumnReference(0), $source, 1, true));
    }

    public function testTextComparisonUsesTheStockNullSafeOperator(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('items'), [new Identifier('label')], [new Varchar(8)]);
        $comparison = new TextComparison(TextOperator::NullSafeEqual, new TextColumnReference(0), new NullLiteral());

        self::assertSame('(`__ztd_source`.`label` <=> NULL)', (new SelectSql())->textComparison($comparison, $source, 0, true));
    }

    public function testSignedCaseEmitsAllSimpleBranchesAndFallback(): void
    {
        $one = new RangeIntegerLiteral('1');
        $null = new NullLiteral();
        $case = new SignedCase([new SimpleCaseArm($one, $null), new SimpleCaseArm($null, $one)], $one, $null);

        self::assertSame('(CASE 1 WHEN 1 THEN NULL WHEN NULL THEN 1 ELSE NULL END)', (new SelectSql())->signedCase($case, null, 0, true));
    }

    public function testCaseOperandEmitsAnExplicitBoolean(): void
    {
        self::assertSame('TRUE', (new SelectSql())->caseOperand(new BooleanLiteral(true), null, 0, true));
    }

    public function testTruthTestEmitsTheResolvedNativeOperation(): void
    {
        $value = new TruthTest(TruthOperator::NotUnknown, new RangeIntegerLiteral('0', 2, true));

        self::assertSame('(-000 IS NOT UNKNOWN)', (new SelectSql())->truthTest($value, null, 0, true));
    }

    public function testIntegerComparisonEmitsNullSafeEqualityAndExactWidenedOperand(): void
    {
        $comparison = new IntegerComparison(
            IntegerOperator::NullSafeEqual,
            new RangeIntegerLiteral('-9223372036854775808'),
            new NullLiteral()
        );

        self::assertSame('(-9223372036854775808 <=> NULL)', (new SelectSql())->integerComparison($comparison, null, 0, true));
    }
}
