<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\LengthArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(LengthArgument::class)]
#[Small]
final class LengthArgumentTest extends TestCase
{
    public function testBytesAnswersTheIntegerOrNull(): void
    {
        self::assertSame([16, null], [(new LengthArgument(new SignedNumber(false, new IntegerConstant('16'))))->bytes(), (new LengthArgument(new StringConstant('Variable')))->bytes()]);
    }

    public function testVariableTellsTheWordVariable(): void
    {
        self::assertSame([false, true], [(new LengthArgument(new SignedNumber(false, new IntegerConstant('16'))))->variable(), (new LengthArgument(new TypeName(new NamedDesignation(new DottedName([new Name('variable')])))))->variable()]);
    }

    public function testFitsOnlyALengthReading(): void
    {
        $length = new LengthArgument(new StringConstant('variable'));
        self::assertSame([true, false], [$length->fits(Reading::Length), $length->fits(Reading::Integer)]);
    }

    public function testDeriveClauseRecordsNothingForANumber(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new LengthArgument(new SignedNumber(false, new IntegerConstant('4'))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheLengthAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new LengthArgument(new StringConstant('variable')))->render($out);
        self::assertSame("'variable'", (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnotherWord(): void
    {
        $this->expectExceptionMessage('A type length is an integer that fits in 32 bits or the word variable.');
        new LengthArgument(new StringConstant('fixed'));
    }
}
