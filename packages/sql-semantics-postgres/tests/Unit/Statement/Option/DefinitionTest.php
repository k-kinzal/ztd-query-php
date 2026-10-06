<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Definition::class)]
#[Small]
final class DefinitionTest extends TestCase
{
    public function testDeriveClauseDerivesTheModifiersOfATypeValue(): void
    {
        $modifier = new Constant(new IntegerConstant('1'));
        $definition = new Definition(new Name('element'), new TypeName(new DecimalDesignation(DecimalKeyword::Numeric, [$modifier])));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $definition->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesNamespaceNameAndValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Definition(new Name('fillfactor'), new SignedNumber(false, new IntegerConstant('70')), new Name('toast')))->render($out);
        self::assertSame('toast.fillfactor = 70', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Definition(new Name('select')))->render($second);
        self::assertSame('select', (new Lexical())->join($second->pieces()));
    }
}
