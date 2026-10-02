<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ComparisonTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(ComparisonTyping::class)]
#[Small]
final class ComparisonTypingTest extends TestCase
{
    public function testResultIsBooleanForAnExactCatalogOperator(): void
    {
        $equal = new OperatorName(new Name('='));
        self::assertEquals(new Known(Builtin::Bool), (new ComparisonTyping())->result((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), $equal, new Known(Builtin::Int4), new Known(Builtin::Int8)));
        self::assertEquals(new Known(Builtin::Bool), (new ComparisonTyping())->result((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), $equal, new Known(Builtin::Unknown), new Known(new Parameterized(Builtin::Numeric, 10))));
        self::assertEquals(new Known(Builtin::Bool), (new ComparisonTyping())->result((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), $equal, new Known(Builtin::Unknown), new NullOnly()));
    }

    public function testResultDependsOnTheOperatorWhenTheCatalogHasNoExactOne(): void
    {
        $equal = new OperatorName(new Name('='));
        $fact = (new ComparisonTyping())->result((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), $equal, new Known(Builtin::Varchar), new Known(Builtin::Varchar));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertInstanceOf(UndeclaredRoutine::class, $fact->missing[0]);
        self::assertInstanceOf(Dependent::class, (new ComparisonTyping())->result((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), $equal, new Known(Builtin::Json), new Known(Builtin::Json)));
    }

    public function testResultPassesOnAnOperandThatIsNotKnown(): void
    {
        $operand = new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('f')))]);
        self::assertSame($operand, (new ComparisonTyping())->result((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new OperatorName(new Name('<')), new Known(Builtin::Int4), $operand));
    }

    public function testBaseReadsTheCatalogTypeOfAnOperand(): void
    {
        self::assertSame(Builtin::Unknown, (new ComparisonTyping())->base(new NullOnly()));
        self::assertSame(Builtin::Interval, (new ComparisonTyping())->base(new Known(new IntervalSpan(IntervalFields::Day))));
        self::assertNull((new ComparisonTyping())->base(new Known(new ArrayOf(Builtin::Int4))));
    }

    public function testCatalogRequiresTheCatalogToBeSearchedFirst(): void
    {
        $less = new OperatorName(new Name('<'));
        self::assertTrue((new ComparisonTyping())->catalog((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false), $less));
        $shadowed = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['app', 'pg_catalog'], [], true);
        self::assertFalse((new ComparisonTyping())->catalog($shadowed, $less));
        self::assertTrue((new ComparisonTyping())->catalog($shadowed, new OperatorName(new Name('<'), [new Name('pg_catalog')], true)));
        self::assertFalse((new ComparisonTyping())->catalog((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false), new OperatorName(new Name('@>'))));
    }

    public function testExactListsThePairsTheCatalogCompares(): void
    {
        self::assertTrue((new ComparisonTyping())->exact(Builtin::Text, Builtin::Text));
        self::assertTrue((new ComparisonTyping())->exact(Builtin::Date, Builtin::Timestamptz));
        self::assertFalse((new ComparisonTyping())->exact(Builtin::Int4, Builtin::Numeric));
        self::assertFalse((new ComparisonTyping())->exact(Builtin::Xml, Builtin::Xml));
    }
}
