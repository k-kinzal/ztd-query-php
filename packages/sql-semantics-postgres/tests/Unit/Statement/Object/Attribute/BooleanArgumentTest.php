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
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(BooleanArgument::class)]
#[Small]
final class BooleanArgumentTest extends TestCase
{
    public function testValueReadsEachSpelling(): void
    {
        self::assertSame([true, false, true, false, true], [(new BooleanArgument())->value(), (new BooleanArgument(new SignedNumber(true, new IntegerConstant('0'))))->value(), (new BooleanArgument(new KeywordWord(new Name('on'))))->value(), (new BooleanArgument(new TypeName(new NamedDesignation(new DottedName([new Name('OFF')])))))->value(), (new BooleanArgument(new StringConstant('True')))->value()]);
    }

    public function testFitsOnlyABooleanReading(): void
    {
        self::assertSame([true, false], [(new BooleanArgument())->fits(Reading::Boolean), (new BooleanArgument())->fits(Reading::Text)]);
    }

    public function testDeriveClauseRecordsNothingForAWord(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new BooleanArgument(new StringConstant('on')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesNothingForAnAttributeGivenAlone(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BooleanArgument())->render($out);
        self::assertSame('', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnotherInteger(): void
    {
        $this->expectExceptionMessage('A Boolean attribute is given alone, as 0 or 1, or as true, false, on or off.');
        new BooleanArgument(new SignedNumber(false, new IntegerConstant('2')));
    }
}
