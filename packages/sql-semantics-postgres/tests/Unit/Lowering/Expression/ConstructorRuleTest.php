<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\ConstructorRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;

#[CoversClass(ConstructorRule::class)]
#[Small]
final class ConstructorRuleTest extends TestCase
{
    public function testRowLowersTheRowOfOverlaps(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $row = (new ConstructorRule($lowering))->row((new PostgreSqlParser('pg-17.2'))->parse('SELECT (1, 2) OVERLAPS ROW(3, 4)')->find('row')[0]);
        self::assertSame([RowSpelling::Implicit, 2], [$row->spelling, count($row->fields)]);
    }

    public function testItemsLowersAnEmptyArray(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $items = (new ConstructorRule($lowering))->items((new PostgreSqlParser('pg-17.2'))->parse('SELECT ARRAY[]::int[]')->find('array_expr')[0]);
        self::assertSame([[], []], [$items->values, $items->nested]);
    }

    public function testCaseExpressionLowersTheOperandAndElse(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $case = (new ConstructorRule($lowering))->caseExpression((new PostgreSqlParser('pg-17.2'))->parse('SELECT CASE a WHEN 1 THEN 2 ELSE 3 END FROM t')->find('case_expr')[0]);
        self::assertNotNull($case->operand);
        self::assertNotNull($case->default);
    }

    public function testOptionalOfTheEmptyProductionIsNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $default = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CASE WHEN true THEN 1 END')->find('case_default')[0];
        self::assertNull((new ConstructorRule($lowering))->optional($default, 'case_default: ELSE a_expr', 'case_default:'));
    }
}
