<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\OperatorTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(OperatorTyping::class)]
#[Small]
final class OperatorTypingTest extends TestCase
{
    public function testBinaryTypesAndPropagatesNull(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $fact = (new OperatorTyping())->binary($context, new OperatorName(new Name('-')), new ScalarFact(new Known(Builtin::Date), Nullability::NotNull), new ScalarFact(new NullOnly(), Nullability::Nullable));
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::Nullable), $fact);
    }

    public function testNamedDependsOnAnOperatorOutsideThePath(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['public', 'pg_catalog'], [], true);
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('+')))]), (new OperatorTyping())->named($context, '+', new Known(Builtin::Int4), new Known(Builtin::Int4)));
    }

    public function testArraysTypesContainment(): void
    {
        self::assertSame(Builtin::Bool, (new OperatorTyping())->arrays('@>', new ArrayOf(Builtin::Int4), new ArrayOf(Builtin::Int4)));
    }

    public function testStructuredTypesArrayConcatenation(): void
    {
        self::assertEquals(new ArrayOf(Builtin::Int4), (new OperatorTyping())->structured('||', new Known(new ArrayOf(Builtin::Int4)), new Known(Builtin::Int4)));
    }

    public function testCatalogTypesDateTimeArithmetic(): void
    {
        self::assertSame(Builtin::Interval, (new OperatorTyping())->catalog('-', new Known(Builtin::Timestamptz), new Known(Builtin::Timestamptz)));
    }

    public function testComparableAcceptsMixedNumbers(): void
    {
        self::assertSame([true, false], [(new OperatorTyping())->comparable(Builtin::Int2, Builtin::Float8), (new OperatorTyping())->comparable(Builtin::Int4, Builtin::Text)]);
    }

    public function testBothIsBooleanUnlessAConditionDepends(): void
    {
        $dependent = (new OperatorTyping())->undeclared('=');
        self::assertEquals([new Known(Builtin::Bool), $dependent], [(new OperatorTyping())->both(new Known(Builtin::Bool), new Known(Builtin::Bool)), (new OperatorTyping())->both(new Known(Builtin::Bool), $dependent)]);
    }

    public function testVisibleNeedsTheCatalogFirst(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertSame([true, false], [(new OperatorTyping())->visible($context, '+'), (new OperatorTyping())->visible($context, '+', new OperatorName(new Name('+'), [new Name('public')], true))]);
    }

    public function testUndeclaredNamesTheRoutine(): void
    {
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('<->')))]), (new OperatorTyping())->undeclared('<->'));
    }

}
