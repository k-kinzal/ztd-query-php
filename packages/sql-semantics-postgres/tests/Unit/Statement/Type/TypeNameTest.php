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
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(TypeName::class)]
#[Small]
final class TypeNameTest extends TestCase
{
    public function testTypeFactIsTheArrayTypeOverTheDesignation(): void
    {
        $type = new TypeName(new KeywordDesignation(TypeKeyword::Bigint), true, new ArraySpecifier([], true));
        $fact = $type->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('bigint[]', $fact->descriptor->name());
    }

    public function testTypeFactPassesOnAMissingDeclaration(): void
    {
        $type = new TypeName(new NamedDesignation(new DottedName([new Name('app'), new Name('t')])), false, new ArraySpecifier([new ArrayBound()]));
        self::assertInstanceOf(Dependent::class, $type->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true)));
    }

    public function testDeriveClauseDerivesTheModifiers(): void
    {
        $modifier = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new TypeName(new DecimalDesignation(DecimalKeyword::Dec, [$modifier])))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesSetofDesignationAndArray(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TypeName(new KeywordDesignation(TypeKeyword::DoublePrecision), true, new ArraySpecifier([new ArrayBound()])))->render($out);
        self::assertSame('SETOF DOUBLE PRECISION []', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnArrayOfAColumnReference(): void
    {
        $this->expectExceptionMessage('A column type reference takes no array part.');
        new TypeName(new ColumnDesignation(new DottedName([new Name('t'), new Name('a')])), false, new ArraySpecifier([new ArrayBound()]));
    }
}
