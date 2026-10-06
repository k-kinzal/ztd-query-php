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
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\OperatorRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest;

#[CoversClass(OperatorRule::class)]
#[Small]
final class OperatorRuleTest extends TestCase
{
    public function testLowerAnswersNullForAPredicate(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 IS NULL')->find('a_expr')[0]);
        self::assertNull((new OperatorRule($lowering))->lower($form));
    }

    public function testOperatorsLowersAConjunction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT true AND NOT false')->find('a_expr')[0]);
        self::assertInstanceOf(BooleanOperation::class, (new OperatorRule($lowering))->operators($form));
    }

    public function testPostfixLowersACastAndADistinctTest(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $cast = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1::text')->find('a_expr')[0]);
        $distinct = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 IS NOT DISTINCT FROM 2')->find('a_expr')[0]);
        self::assertInstanceOf(Cast::class, (new OperatorRule($lowering))->postfix($cast));
        self::assertInstanceOf(DistinctTest::class, (new OperatorRule($lowering))->postfix($distinct));
    }
}
