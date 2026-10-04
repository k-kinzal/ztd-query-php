<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(CallTyping::class)]
#[Small]
final class CallTypingTest extends TestCase
{
    public function testCallTypesAnExactCatalogMatch(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new CallTyping())->call($derivation, new FunctionCall(new DottedName([new Name('abs')]), [new PositionalArgument(new Constant(new IntegerConstant('1')))]), [new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull)]);
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull), $fact);
    }

    public function testCallDependsWhenAnotherSchemaIsSearchedFirst(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['public', 'pg_catalog'], [], true));
        $fact = (new CallTyping())->call($derivation, new FunctionCall(new DottedName([new Name('abs')]), [new PositionalArgument(new Constant(new IntegerConstant('1')))]), [new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull)]);
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('abs')))]), $fact->type);
    }

    public function testCallDependsOnTheCurrentDatabaseForACatalogQualifier(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new CallTyping())->call($derivation, new FunctionCall(new DottedName([new Name('db'), new Name('pg_catalog'), new Name('now')])), []);
        self::assertEquals(new Dependent([new SessionState('the name of the current database'), new UndeclaredRoutine(new QualifiedName(new Name('now'), new Name('pg_catalog'), new Name('db')))]), $fact->type);
    }

    public function testCallReportsAnImproperName(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new CallTyping())->call($derivation, new FunctionCall(new DottedName([new Name('a'), new Name('b'), new Name('c'), new Name('d')])), []);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(ImproperName::class, $derivation->facts()->diagnostics[0]);
    }

    public function testCallLeavesNamedArgumentsToTheRoutine(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new CallTyping())->call($derivation, new FunctionCall(new DottedName([new Name('abs')]), [new NamedArgument(new Name('x'), new Constant(new IntegerConstant('1')))]), [new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull)]);
        self::assertInstanceOf(Dependent::class, $fact->type);
    }

    public function testCatalogMatchesThroughImplicitCasts(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new CallTyping())->catalog($derivation->context, 'substring', [new ScalarFact(new Known(Builtin::Varchar), Nullability::NotNull), new ScalarFact(new Known(Builtin::Int2), Nullability::Nullable)]);
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::Nullable), $fact);
    }

    public function testCatalogPassesAnInvalidArgumentOn(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $invalid = new Invalid(new ImproperName(new DottedName([new Name('a')])));
        $fact = (new CallTyping())->catalog($derivation->context, 'upper', [new ScalarFact($invalid, Nullability::Dependent)]);
        self::assertSame($invalid, $fact->type);
    }

    public function testRowAnswersNullForATypeOutsideTheTable(): void
    {
        self::assertNull((new CallTyping())->row('upper', [new ScalarFact(new Known(new Parameterized(Builtin::Varchar, 3)), Nullability::NotNull)], false));
        self::assertNotNull((new CallTyping())->row('upper', [new ScalarFact(new Known(new Parameterized(Builtin::Varchar, 3)), Nullability::NotNull)], true));
    }

    public function testResultAppliesTheNullRule(): void
    {
        $typing = new CallTyping();
        self::assertEquals(new ScalarFact(new Known(Builtin::Int8), Nullability::Nullable), $typing->result(['int8', 'a', 'Y', []], []));
        self::assertEquals(new ScalarFact(new Known(Builtin::Uuid), Nullability::NotNull), $typing->result(['uuid', 's', 'N', []], []));
    }

    public function testStrictIsNullableWhenAnArgumentIs(): void
    {
        self::assertSame([Nullability::Nullable, Nullability::NotNull], [(new CallTyping())->strict([new ScalarFact(new NullOnly(), Nullability::NotNull)]), (new CallTyping())->strict([new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull)])]);
    }

    public function testInputsMergesTheMissingInputsOfDependentArguments(): void
    {
        $missing = new UndeclaredRoutine(new QualifiedName(new Name('f')));
        $fact = (new CallTyping())->inputs([new ScalarFact(new Dependent([$missing]), Nullability::Dependent), new ScalarFact(new Dependent([$missing]), Nullability::Dependent)]);
        self::assertEquals(new Dependent([$missing, $missing]), $fact?->type);
        self::assertNull((new CallTyping())->inputs([new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull)]));
    }

    public function testCatalogFirstReadsTheSearchPath(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['public', 'pg_catalog'], [], true));
        self::assertFalse((new CallTyping())->catalogFirst($derivation->context));
    }
}
