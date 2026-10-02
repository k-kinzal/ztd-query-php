<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(CharacterDesignation::class)]
#[Small]
final class CharacterDesignationTest extends TestCase
{
    public function testBuiltinIsVaryingForVarcharAndTheVaryingKeyword(): void
    {
        self::assertSame(Builtin::Bpchar, (new CharacterDesignation(CharacterKeyword::NationalCharacter))->builtin());
        self::assertSame(Builtin::Varchar, (new CharacterDesignation(CharacterKeyword::Nchar, true))->builtin());
        self::assertSame(Builtin::Varchar, (new CharacterDesignation(CharacterKeyword::Varchar))->builtin());
    }

    public function testTypeFactDefaultsToOneCharacterAsATypeOnly(): void
    {
        $character = new CharacterDesignation(CharacterKeyword::Char);
        $type = $character->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false);
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('character(1)', $type->descriptor->name());
        self::assertEquals(new Known(Builtin::Bpchar), $character->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), true));
        self::assertEquals(new Known(Builtin::Varchar), (new CharacterDesignation(CharacterKeyword::Varchar))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
    }

    public function testTypeFactChecksTheLength(): void
    {
        $known = (new CharacterDesignation(CharacterKeyword::Varchar, false, new IntegerConstant('10')))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false);
        self::assertInstanceOf(Known::class, $known);
        self::assertSame('character varying(10)', $known->descriptor->name());
        self::assertInstanceOf(Invalid::class, (new CharacterDesignation(CharacterKeyword::Char, false, new IntegerConstant('0')))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
    }

    public function testCatalogNameIsBpcharOrVarchar(): void
    {
        self::assertSame('bpchar', (new CharacterDesignation(CharacterKeyword::Nchar))->catalogName()->value);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new CharacterDesignation(CharacterKeyword::Char))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheKeywordsVaryingAndTheLength(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new CharacterDesignation(CharacterKeyword::NationalChar, true, new IntegerConstant('5')))->render($out);
        self::assertSame('NATIONAL CHAR VARYING (5)', (new Lexical())->join($out->pieces()));
    }
}
