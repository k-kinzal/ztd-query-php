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
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\PrimaryRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\GroupingFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Subscript;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;

#[CoversClass(PrimaryRule::class)]
#[Small]
final class PrimaryRuleTest extends TestCase
{
    public function testLowerLowersGrouping(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT GROUPING(a, b) FROM t')->find('c_expr')[0]);
        self::assertInstanceOf(GroupingFunction::class, (new PrimaryRule($lowering))->lower($form));
    }

    public function testParameterWithoutStepsIsTheParameter(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT $1')->find('c_expr')[0]);
        $parameter = (new PrimaryRule($lowering))->parameter($form);
        self::assertInstanceOf(PositionalParameter::class, $parameter);
        self::assertSame('1', $parameter->number);
    }

    public function testAppliedKeepsAValueWithoutSteps(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $value = new NullLiteral();
        self::assertSame($value, (new PrimaryRule($lowering))->applied($value, []));
    }

    public function testColumnReferenceSplitsAtTheFirstSubscript(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $reference = (new PrimaryRule($lowering))->columnReference((new PostgreSqlParser('pg-17.2'))->parse('SELECT t.a[1].b FROM t')->find('columnref')[0]);
        self::assertInstanceOf(Indirection::class, $reference);
        self::assertInstanceOf(ColumnReference::class, $reference->base);
        self::assertCount(2, $reference->base->parts);
    }

    public function testColumnReferenceKeepsAStepAfterAStar(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $reference = (new PrimaryRule($lowering))->columnReference((new PostgreSqlParser('pg-17.2'))->parse('SELECT f(t.*.a) FROM t')->find('columnref')[0]);
        self::assertInstanceOf(Indirection::class, $reference);
        self::assertInstanceOf(ColumnStar::class, $reference->base);
    }

    public function testIndirectionOfNoStepIsEmpty(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertSame([], (new PrimaryRule($lowering))->indirection((new PostgreSqlParser('pg-17.2'))->parse('SELECT $1')->find('opt_indirection')[0]));
    }

    public function testStepLowersAStar(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $form = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT ($1).*')->find('indirection_el')[0]);
        self::assertInstanceOf(AllFields::class, (new PrimaryRule($lowering))->step($form));
    }

    public function testBoundOfAnOmittedBoundIsNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertNull((new PrimaryRule($lowering))->bound((new PostgreSqlParser('pg-17.2'))->parse('SELECT $1[:]')->find('opt_slice_bound')[0]));
        self::assertInstanceOf(Subscript::class, (new PrimaryRule($lowering))->step($lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT $1[1]')->find('indirection_el')[0])));
    }
}
