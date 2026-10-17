<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\ConstructRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(ConstructRule::class)]
#[Medium]
final class ConstructRuleTest extends TestCase
{
    public function testAtLocalIsRejectedBeforePublishingAnExpression(): void
    {
        $failure = null;
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT CAST(1 AT LOCAL AS DATE), BINARY 2');
        } catch (\SqlSemantics\Construction\RejectedInput $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(\SqlSemantics\Construction\RejectedInput::class, $failure);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL'), $failure->problem);
        self::assertFalse($failure->aborts);
        self::assertSame(strlen('SELECT CAST(1 AT LOCAL AS DATE)'), $failure->end);
    }

    public function testLowerLowersTheKeywordConstructs(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new ConstructRule($lowering);
        $productions = $platform->productions($profile);
        $parser = $platform->parser($profile);
        $convert = $rule->lower($productions->form($parser->parse('SELECT CONVERT(1, SIGNED)')->find('simple_expr')[0]));

        self::assertInstanceOf(Cast::class, $convert);
        self::assertFalse($convert->array);
        self::assertInstanceOf(CharsetConversion::class, $rule->lower($productions->form($parser->parse('SELECT CONVERT(1 USING latin1)')->find('simple_expr')[0])));
        self::assertInstanceOf(BinaryCast::class, $rule->lower($productions->form($parser->parse('SELECT BINARY 1')->find('simple_expr')[0])));
        self::assertInstanceOf(FullTextSearch::class, $rule->lower($productions->form($parser->parse("SELECT MATCH (a) AGAINST ('x')")->find('simple_expr')[0])));
        self::assertInstanceOf(CaseExpression::class, $rule->lower($productions->form($parser->parse('SELECT CASE WHEN 1 THEN 2 END')->find('simple_expr')[0])));
    }

    public function testZoneLowersTheTimeZoneAndThePrecision(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $zone = (new ConstructRule($lowering))->zone($platform->productions($profile)->form($platform->parser($profile)->parse("SELECT CAST(1 AT TIME ZONE INTERVAL '+00:00' AS DATETIME(2))")->find('simple_expr')[0]));

        self::assertSame(['+00:00', true, '2'], [$zone->zone->value, $zone->interval, $zone->precision]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerPrecisionRefusals(): iterable
    {
        yield 'continuing 8.0' => ['mysql-8.0.44', false];
        yield 'continuing 8.1' => ['mysql-8.1.0', false];
        yield 'aborting 8.4' => ['mysql-8.4.7', true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerPrecisionRefusals')]
    public function testZoneRefusesAnExcessivePrecisionBeforePublishingAnExpression(string $release, bool $aborts): void
    {
        $failure = null;
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release))->analyze("SELECT CAST(NULL AT TIME ZONE 'UTC' AS DATETIME(7)), BINARY 1");
        } catch (\SqlSemantics\Construction\RejectedInput $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(\SqlSemantics\Construction\RejectedInput::class, $failure);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision(7, 'CAST'), $failure->problem);
        self::assertSame($aborts, $failure->aborts);
        self::assertSame(strlen("SELECT CAST(NULL AT TIME ZONE 'UTC' AS DATETIME(7))"), $failure->end);
    }

    /**
     * @return iterable<string, array{string, string, int, int, bool}>
     */
    public static function providerCastPrecisions(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.4.7'] as $release) {
            $aborts = !in_array($release, ['mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0'], true);
            yield $release . ' temporal' => [$release, 'CAST(NULL AS TIME(7))', 7, 6, $aborts];
            yield $release . ' convert' => [$release, 'CONVERT(NULL,DATETIME(7))', 7, 6, $aborts];
        }
        yield 'floating limit' => ['mysql-8.4.7', 'CAST(NULL AS FLOAT(54))', 54, 53, true];
        yield 'floating overflow' => ['mysql-8.4.7', 'CAST(NULL AS FLOAT(4294967296))', 0, 53, true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCastPrecisions')]
    public function testCastPublishesAPrecisionRefusalWithoutAnInvalidScalar(string $release, string $cast, int $precision, int $maximum, bool $aborts): void
    {
        $failure = null;
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release))->analyze('SELECT ' . $cast . ', BINARY 1');
        } catch (\SqlSemantics\Construction\RejectedInput $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(\SqlSemantics\Construction\RejectedInput::class, $failure);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision($precision, 'CAST', $maximum), $failure->problem);
        self::assertSame($aborts, $failure->aborts);
        self::assertSame(strlen('SELECT ' . $cast), $failure->end);
    }

    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function providerArrayTargets(): iterable
    {
        foreach (['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.4.7', 'mysql-9.1.0'] as $release) {
            foreach (['JSON' => 'CAST-ing data to array of JSON', 'FLOAT(54)' => 'CAST-ing data to array of FLOAT', 'NCHAR(2)' => 'specifying charset for multi-valued index', 'BINARY' => 'CAST-ing data to array of char/binary BLOBs'] as $type => $problem) {
                yield $release . ' ' . $type => [$release, $type, $problem, !in_array($release, ['mysql-8.0.44', 'mysql-8.1.0'], true)];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerArrayTargets')]
    public function testCastPublishesAnElementTypeRefusalWithoutAnInvalidScalar(string $release, string $type, string $problem, bool $aborts): void
    {
        $failure = null;
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release))->analyze('SELECT CAST(NULL AS ' . $type . ' ARRAY), BINARY 1');
        } catch (\SqlSemantics\Construction\RejectedInput $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(\SqlSemantics\Construction\RejectedInput::class, $failure);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet($problem), $failure->problem);
        self::assertSame($aborts, $failure->aborts);
        self::assertSame(strlen('SELECT CAST(NULL AS ' . $type . ' ARRAY)'), $failure->end);
    }

    public function testOptionalLowersTheOperandAndTheElseOfCase(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT CASE 1 WHEN 2 THEN 3 ELSE 4 END');
        $rule = new ConstructRule($lowering);

        self::assertInstanceOf(NumberLiteral::class, $rule->optional($tree->find('opt_expr')[0]));
        self::assertInstanceOf(NumberLiteral::class, $rule->optional($tree->find('opt_else')[0]));
        self::assertNull($rule->optional($platform->parser($profile)->parse('SELECT CASE WHEN 2 THEN 3 END')->find('opt_else')[0]));
    }

    public function testBranchesKeepsTheOrderOfTheWhenBranches(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $branches = (new ConstructRule($lowering))->branches($platform->parser($profile)->parse('SELECT CASE WHEN 1 THEN 2 WHEN 3 THEN 4 WHEN 5 THEN 6 END')->find('when_list')[0]);

        self::assertCount(3, $branches);
        self::assertInstanceOf(NumberLiteral::class, $branches[2]->result);
        self::assertSame('6', $branches[2]->result->text);
    }

    public function testFlagTellsWhetherArrayIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new ConstructRule($lowering);

        self::assertSame([true, false], [$rule->flag($platform->parser($profile)->parse('SELECT CAST(1 AS SIGNED ARRAY)')->find('opt_array_cast')[0]), $rule->flag($platform->parser($profile)->parse('SELECT CAST(1 AS SIGNED)')->find('opt_array_cast')[0])]);
    }

    public function testColumnsLowersTheColumnsWithAndWithoutParentheses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new ConstructRule($lowering);

        self::assertCount(2, $rule->columns($platform->parser($profile)->parse("SELECT MATCH a, b AGAINST ('x')")->find('ident_list_arg')[0]));
        self::assertCount(1, $rule->columns($platform->parser($profile)->parse("SELECT MATCH (a) AGAINST ('x')")->find('ident_list_arg')[0]));
    }

    public function testModeLowersTheSearchModes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new ConstructRule($lowering);
        $parser = $platform->parser($profile);

        self::assertSame(
            [FullTextMode::NaturalLanguage, FullTextMode::QueryExpansion, FullTextMode::Boolean],
            [
                $rule->mode($parser->parse("SELECT MATCH (a) AGAINST ('x' IN NATURAL LANGUAGE MODE)")->find('fulltext_options')[0]),
                $rule->mode($parser->parse("SELECT MATCH (a) AGAINST ('x' WITH QUERY EXPANSION)")->find('fulltext_options')[0]),
                $rule->mode($parser->parse("SELECT MATCH (a) AGAINST ('x' IN BOOLEAN MODE)")->find('fulltext_options')[0]),
            ],
        );
    }

    public function testSearchKeepsTheParenthesesAndTheStatedMode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT MATCH a, b AGAINST ('x' IN NATURAL LANGUAGE MODE)")->find('simple_expr')[0];
        $search = (new ConstructRule($lowering))->search($lowering->form($node));

        self::assertCount(2, $search->columns);
        self::assertSame(OptionalWords::Omitted, $search->parentheses);
        self::assertSame(OptionalWords::Written, $search->stated);
        self::assertSame(FullTextMode::NaturalLanguage, $search->mode);
    }

    public function testCharsetRetainsARowAsAStoredOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() SELECT CONVERT((1,2) USING utf8mb4)');
        self::assertSame('CREATE PROCEDURE p() SELECT CONVERT((1, 2) USING utf8mb4)', $operation->toString());
    }

    public function testCaseExpressionRetainsAnUnselectedRowResult(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() SELECT CASE WHEN 0 THEN (1,2) END');
        self::assertSame('CREATE PROCEDURE p() SELECT CASE WHEN 0 THEN (1, 2) END', $operation->toString());
    }
}
