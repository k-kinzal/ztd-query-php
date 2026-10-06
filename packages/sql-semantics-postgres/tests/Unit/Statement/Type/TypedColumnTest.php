<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

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
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\BitDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TypedColumn::class)]
#[Small]
final class TypedColumnTest extends TestCase
{
    public function testDeriveClauseDerivesTheModifiersOfTheType(): void
    {
        $modifier = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new TypedColumn(new Name('a'), new TypeName(new BitDesignation(false, [$modifier]))))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesNameTypeAndCollation(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TypedColumn(new Name('Name'), new TypeName(new NamedDesignation(new DottedName([new Name('text')]))), new DottedName([new Name('C')])))->render($out);
        self::assertSame('"Name" text COLLATE "C"', (new Lexical())->join($out->pieces()));
    }
}
