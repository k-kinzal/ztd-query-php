<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(PositionalParameter::class)]
#[Small]
final class PositionalParameterTest extends TestCase
{
    public function testBoundIsTheNumberWithinTheLimitOfTheServer(): void
    {
        self::assertSame(1, (new PositionalParameter('1'))->bound(GrammarRelease::PostgreSql172));
        self::assertNull((new PositionalParameter('0'))->bound(GrammarRelease::PostgreSql172));
        self::assertNull((new PositionalParameter('268435456'))->bound(GrammarRelease::PostgreSql172));
    }

    public function testBoundReducesTheNumberToThirtyTwoBitsInPostgreSql16(): void
    {
        self::assertSame(1, (new PositionalParameter('4294967297'))->bound(GrammarRelease::PostgreSql166));
        self::assertNull((new PositionalParameter('4294967295'))->bound(GrammarRelease::PostgreSql166));
        self::assertNull((new PositionalParameter('99999999999999999999'))->bound(GrammarRelease::PostgreSql166));
    }

    public function testOutputNameIsNone(): void
    {
        self::assertNull((new PositionalParameter('1'))->outputName());
    }

    public function testDeriveScalarDependsOnTheBoundValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new PositionalParameter('2'), $derivation->environment());
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the value bound to parameter $2', $fact->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $fact->nullability);
    }

    public function testDeriveScalarReportsParameterZero(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new PositionalParameter('0'), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame('There is no parameter $0.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesTheMarker(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new PositionalParameter('12'))->render($out);
        self::assertSame('$12', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsLeadingZeros(): void
    {
        $this->expectExceptionMessage('A parameter number is canonical decimal digits.');
        new PositionalParameter('01');
    }
}
