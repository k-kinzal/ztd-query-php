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
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\PredicateRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;

#[CoversClass(PredicateRule::class)]
#[Small]
final class PredicateRuleTest extends TestCase
{
    public function testLowerLowersAPatternWithAnEscape(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse("SELECT 'a' NOT SIMILAR TO 'b' ESCAPE 'c'")->find('a_expr')[0]);
        $match = (new PredicateRule($lowering))->lower($form);
        self::assertInstanceOf(PatternMatch::class, $match);
        self::assertTrue($match->negated);
        self::assertNotNull($match->escape);
    }

    public function testLowerLowersAPostfixNullTest(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 NOTNULL')->find('a_expr')[0]);
        self::assertInstanceOf(NullTest::class, (new PredicateRule($lowering))->lower($form));
    }

    public function testRangeDropsAsymmetric(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 BETWEEN ASYMMETRIC 0 AND 2')->find('a_expr')[0]);
        self::assertFalse((new PredicateRule($lowering))->range($form)?->symmetric);
    }

    public function testAsymmetricAcceptsTheEmptyProduction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $flag = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 BETWEEN 0 AND 2')->find('opt_asymmetric')[0];
        (new PredicateRule($lowering))->asymmetric($flag);
        self::assertSame('opt_asymmetric', $flag->name);
    }

    public function testTestsLowersDefault(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('UPDATE t SET a = DEFAULT + 1')->find('a_expr')[1]);
        self::assertInstanceOf(DefaultRequest::class, (new PredicateRule($lowering))->tests($form));
    }

    public function testJsonLowersTheItemKind(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse("SELECT '1' IS NOT JSON SCALAR")->find('a_expr')[0]);
        $test = (new PredicateRule($lowering))->json($form, true);
        self::assertSame([JsonItemKind::JsonScalar, null], [$test->kind, $test->uniqueness]);
    }
}
