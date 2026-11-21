<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TypeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

#[CoversClass(TypeArgument::class)]
#[Small]
final class TypeArgumentTest extends TestCase
{
    public function testTypeFactLooksUpEachSpelling(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        self::assertEquals([true, true, true], [(new TypeArgument(new TypeName(new KeywordDesignation(TypeKeyword::Integer))))->typeFact($derivation->context) instanceof Known, (new TypeArgument(new StringConstant('int4')))->typeFact($derivation->context) instanceof Known, (new TypeArgument(new KeywordWord(new Name('user'))))->typeFact($derivation->context) instanceof Dependent]);
    }

    public function testFitsOnlyATypeReading(): void
    {
        self::assertSame([true, false], [(new TypeArgument(new StringConstant('int4')))->fits(Reading::Type), (new TypeArgument(new StringConstant('int4')))->fits(Reading::CreatedType)]);
    }

    public function testDeriveClauseDerivesTheModifiers(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $modifier = new Constant(new IntegerConstant('1'));
        (new TypeArgument(new TypeName(new NamedDesignation(new DottedName([new Name('f')]), [$modifier]))))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesTheTypeAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TypeArgument(new TypeName(new KeywordDesignation(TypeKeyword::Integer), false, new ArraySpecifier([new ArrayBound()]))))->render($out);
        self::assertSame('INTEGER []', (new Lexical())->join($out->pieces()));
    }
}
