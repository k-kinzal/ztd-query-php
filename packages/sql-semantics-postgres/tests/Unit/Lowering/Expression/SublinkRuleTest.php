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
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\SublinkRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\MatchKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedArray;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;

#[CoversClass(SublinkRule::class)]
#[Small]
final class SublinkRuleTest extends TestCase
{
    public function testLowerLowersAComparisonWithAnArray(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 = ANY ($1)')->find('a_expr')[0]);
        self::assertInstanceOf(QuantifiedArray::class, (new SublinkRule($lowering))->lower($form));
    }

    public function testMembershipLowersAList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $in = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 IN (1, 2)')->find('in_expr')[0];
        self::assertInstanceOf(InList::class, (new SublinkRule($lowering))->membership(new NullLiteral(), false, $in));
    }

    public function testComparatorLowersAKeyword(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $operator = (new PostgreSqlParser('pg-17.2'))->parse("SELECT 'a' NOT ILIKE ALL ($1)")->find('subquery_Op')[0];
        self::assertSame(MatchKeyword::NotILike, (new SublinkRule($lowering))->comparator($operator));
    }

    public function testQuantifierLowersSome(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $quantifier = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 = SOME ($1)')->find('sub_type')[0];
        self::assertSame(Quantifier::Some, (new SublinkRule($lowering))->quantifier($quantifier));
    }
}
