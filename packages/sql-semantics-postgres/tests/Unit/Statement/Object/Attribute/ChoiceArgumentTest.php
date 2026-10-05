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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ChoiceArgument::class)]
#[Small]
final class ChoiceArgumentTest extends TestCase
{
    public function testFitsTheReadingOfItsWordSet(): void
    {
        $choice = new ChoiceArgument(new StringConstant('safe'), Parallelism::Safe);
        self::assertSame([true, false], [$choice->fits(Reading::Parallelism), $choice->fits(Reading::Alignment)]);
    }

    public function testDeriveClauseDerivesTheModifiersOfATypeName(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $modifier = new Constant(new IntegerConstant('1'));
        (new ChoiceArgument(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]), [$modifier])), Alignment::Int))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesTheValueAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ChoiceArgument(new TypeName(new KeywordDesignation(TypeKeyword::Integer)), Alignment::Int))->render($out);
        self::assertSame('INTEGER', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAMemberTheTextDoesNotName(): void
    {
        $this->expectExceptionMessage('The member of a choice is the one the written text names.');
        new ChoiceArgument(new StringConstant('SAFE'), Parallelism::Safe);
    }
}
