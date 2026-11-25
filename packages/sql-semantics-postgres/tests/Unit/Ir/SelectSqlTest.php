<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(SelectSql::class)]
#[Small]
final class SelectSqlTest extends TestCase
{
    public function testCollatedRetainsTheExplicitTypeOfNull(): void
    {
        $expression = new CollatedText(new NullLiteral(), TextCollation::C);

        self::assertSame('(CAST(NULL AS pg_catalog.text) COLLATE pg_catalog."C")', (new SelectSql())->collated($expression, null, 0, true));
    }

    public function testColumnReadsTheNamedPhysicalTextField(): void
    {
        $source = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('label')], false, [ColumnKind::Text]);
        $reference = new TextColumnReference(0);

        self::assertSame('"__ztd_source"."label"', (new SelectSql())->column($reference, $source));
    }

    public function testRenderAddsOutputAliases(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('"'))]);
        self::assertSame('SELECT CAST(2 AS pg_catalog.int4) AS """"', (new SelectSql())->render($select));
    }

    public function testExpressionKeepsNegativeValues(): void
    {
        self::assertSame('CAST(-2 AS pg_catalog.int4)', (new SelectSql())->expression(new IntegerLiteral('-2'), null));
    }

    public function testAliasSeparatesJoinedInputOrdinals(): void
    {
        $sql = new SelectSql();

        self::assertSame('"__ztd_source_1"', $sql->alias(1));
    }

    public function testFromKeepsTheJoinAssociation(): void
    {
        $a = new TableSource(new Identifier('public'), new Identifier('a'), []);
        $b = new TableSource(new Identifier('public'), new Identifier('b'), []);
        $joined = new Join(JoinKind::Cross, $a, $b);

        self::assertSame('("public"."a" AS "__ztd_source" CROSS JOIN "public"."b" AS "__ztd_source_1")', (new SelectSql())->from($joined));
    }

    public function testPredicateEmitsNullInItsOwnRole(): void
    {
        self::assertSame('NULL', (new SelectSql())->predicate(new NullLiteral(), null));
    }

    public function testIntegerCaseKeepsArmOrderAndAbsentElse(): void
    {
        $value = new IntegerCase([
            new IntegerCaseArm(new BooleanLiteral(true), new NullLiteral()),
            new IntegerCaseArm(new BooleanLiteral(false), new IntegerLiteral('2')),
        ]);

        self::assertSame(
            '(CASE WHEN TRUE THEN CAST(NULL AS pg_catalog.int4) WHEN FALSE THEN CAST(2 AS pg_catalog.int4) END)',
            (new SelectSql())->integerCase($value, null, 0, true)
        );
    }

    public function testIntegerOrNullTypesExplicitNull(): void
    {
        self::assertSame('CAST(NULL AS pg_catalog.int4)', (new SelectSql())->integerOrNull(new NullLiteral(), null, 0, true));
    }
    public function testTruthTestEmitsTheSelectedBuiltInPredicate(): void
    {
        $test = new TruthTest(TruthOperator::IsNotFalse, new NullLiteral());

        self::assertSame('(NULL IS NOT FALSE)', (new SelectSql())->truthTest($test, null, 0, true));
    }

    public function testIntegerDistinctnessCombinesQualifiedEqualityAndBothNullTests(): void
    {
        $value = new IntegerDistinctness(new IntegerLiteral('1'), new NullLiteral());
        $expected = '(NOT COALESCE((CAST(1 AS pg_catalog.int4) OPERATOR(pg_catalog.=) CAST(NULL AS pg_catalog.int4)), '
            . '(CAST(1 AS pg_catalog.int4) IS NULL AND CAST(NULL AS pg_catalog.int4) IS NULL)))';

        self::assertSame($expected, (new SelectSql())->integerDistinctness($value, null, 0, true));
    }

    public function testIntegerMembershipPreservesTypedNullAndNegation(): void
    {
        $test = new IntegerMembership(new IntegerLiteral('2'), [new NullLiteral(), new IntegerLiteral('2')], true);
        $expected = '(NOT (CAST(2 AS pg_catalog.int4) OPERATOR(pg_catalog.=) ANY ('
            . 'CAST(ARRAY[CAST(NULL AS pg_catalog.int4), CAST(2 AS pg_catalog.int4)] AS pg_catalog.int8[]))))';

        self::assertSame($expected, (new SelectSql())->integerMembership($test, null, 0, true));
    }

    public function testIntegerRangePreservesBothSymmetricIntervalsAndNegation(): void
    {
        $range = new IntegerRange(new IntegerLiteral('1'), new IntegerLiteral('2'), new NullLiteral(), true, true);
        $expected = '(NOT (((CAST(1 AS pg_catalog.int4) OPERATOR(pg_catalog.>=) CAST(2 AS pg_catalog.int4))'
            . ' AND (CAST(1 AS pg_catalog.int4) OPERATOR(pg_catalog.<=) CAST(NULL AS pg_catalog.int4))) OR '
            . '((CAST(1 AS pg_catalog.int4) OPERATOR(pg_catalog.>=) CAST(NULL AS pg_catalog.int4))'
            . ' AND (CAST(1 AS pg_catalog.int4) OPERATOR(pg_catalog.<=) CAST(2 AS pg_catalog.int4)))))';

        self::assertSame($expected, (new SelectSql())->integerRange($range, null));
    }

    public function testLocalColumnNamesTheResultOrdinal(): void
    {
        self::assertSame('"__ztd_c3"', (new SelectSql())->localColumn(3));
    }


    public function testTextComparisonRenderPinsTheExactOperatorAndCollation(): void
    {
        $test = new TextComparison(TextOperator::Less, new NullLiteral(), new TextLiteral('Z'), TextCollation::C);
        self::assertSame(
            "((CAST(NULL AS pg_catalog.text) COLLATE pg_catalog.\"C\") OPERATOR(pg_catalog.<) CAST('Z' AS pg_catalog.text))",
            (new SelectSql())->textComparison($test, null, 0, true)
        );
    }
}
