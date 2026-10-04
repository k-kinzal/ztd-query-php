<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(FunctionCall::class)]
#[Small]
final class FunctionCallTest extends TestCase
{
    public function testRejectsArgumentsNextToTheStar(): void
    {
        $this->expectExceptionMessage('The star form takes no argument, DISTINCT, VARIADIC or ordering inside the parentheses.');
        new FunctionCall(new DottedName([new Name('count')]), [new PositionalArgument(new NullLiteral())], true);
    }

    public function testRejectsWithinGroupWithDistinct(): void
    {
        $this->expectExceptionMessage('WITHIN GROUP holds an ordering and goes with neither DISTINCT nor VARIADIC.');
        new FunctionCall(new DottedName([new Name('f')]), [new PositionalArgument(new NullLiteral())], false, true, false, [new SortItem(new Constant(new IntegerConstant('1')))], true);
    }

    public function testRejectsAnOrderingWithoutArguments(): void
    {
        $this->expectExceptionMessage('DISTINCT, VARIADIC and an ordering inside the parentheses need an argument.');
        new FunctionCall(new DottedName([new Name('f')]), [], false, false, false, [new SortItem(new Constant(new IntegerConstant('1')))]);
    }

    public function testOutputNameIsTheLastPartOfTheName(): void
    {
        self::assertSame('lower', (new FunctionCall(new DottedName([new Name('pg_catalog'), new Name('lower')])))->outputName()->value);
    }

    public function testDeriveScalarTypesACatalogFunction(): void
    {
        $call = new FunctionCall(new DottedName([new Name('upper')]), [new PositionalArgument(new Constant(new StringConstant('x')))]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertEquals(new Known(Builtin::Text), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarTypesTheStarAggregate(): void
    {
        $call = new FunctionCall(new DottedName([new Name('count')]), star: true);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int8), Nullability::NotNull), $fact);
    }

    public function testDeriveScalarDependsOnAnUndeclaredRoutine(): void
    {
        $call = new FunctionCall(new DottedName([new Name('public'), new Name('lower')]), [new PositionalArgument(new Constant(new StringConstant('x')))]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('lower'), new Name('public')))]), $fact->type);
        self::assertSame(Nullability::Dependent, $fact->nullability);
    }

    public function testDeriveScalarDerivesTheClauses(): void
    {
        $filter = new BooleanLiteral(true);
        $partition = new Constant(new IntegerConstant('1'));
        $call = new FunctionCall(new DottedName([new Name('sum')]), [new PositionalArgument(new Constant(new IntegerConstant('1')))], filter: $filter, over: new WindowSpecification(null, [$partition]));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($filter));
        self::assertTrue($derivation->facts()->covers($partition));
        self::assertEquals(new Known(Builtin::Int8), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarReportsAMisusedClause(): void
    {
        $call = new FunctionCall(new DottedName([new Name('row_number')]));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame('window function row_number requires an OVER clause', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesEveryPartInOrder(): void
    {
        $call = new FunctionCall(new DottedName([new Name('percentile_cont')]), [new PositionalArgument(new Constant(new IntegerConstant('1')))], false, false, false, [new SortItem(new Constant(new IntegerConstant('1')))], true, new BooleanLiteral(true), new Name('w'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $call->render($out);
        self::assertSame('percentile_cont(1) WITHIN GROUP (ORDER BY 1) FILTER (WHERE TRUE) OVER w', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesDistinctVariadicAndAnInnerOrdering(): void
    {
        $call = new FunctionCall(new DottedName([new Name('s'), new Name('f')]), [new PositionalArgument(new Constant(new IntegerConstant('1'))), new PositionalArgument(new Constant(new IntegerConstant('1')))], false, false, true, [new SortItem(new Constant(new IntegerConstant('1')))], false, null, new WindowSpecification());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $call->render($out);
        self::assertSame('s.f(1, VARIADIC 1 ORDER BY 1) OVER ()', (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesANameThatIsAColumnNameKeyword(): void
    {
        $call = new FunctionCall(new DottedName([new Name('coalesce')]), [], true);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $call->render($out);
        self::assertSame('"coalesce"(*)', (new Lexical())->join($out->pieces()));
    }
}
