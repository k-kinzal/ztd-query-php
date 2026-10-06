<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Character::class)]
#[Small]
final class CharacterTest extends TestCase
{
    public function testNameSpellsTheKeywordsOfEachKind(): void
    {
        self::assertSame('CHAR', (new Character(CharacterKind::Char, '3'))->name());
        self::assertSame('VARCHAR', (new Character(CharacterKind::VarChar, '255'))->name());
        self::assertSame('CHAR VARYING', (new Character(CharacterKind::CharVarying, '10'))->name());
        self::assertSame('TINYTEXT', (new Character(CharacterKind::TinyText))->name());
        self::assertSame('TEXT', (new Character(CharacterKind::Text, '100'))->name());
        self::assertSame('MEDIUMTEXT', (new Character(CharacterKind::MediumText))->name());
        self::assertSame('LONGTEXT', (new Character(CharacterKind::LongText))->name());
        self::assertSame('LONG', (new Character(CharacterKind::Long))->name());
        self::assertSame('LONG VARCHAR', (new Character(CharacterKind::LongVarChar))->name());
        self::assertSame('LONG CHAR VARYING', (new Character(CharacterKind::LongCharVarying))->name());
    }

    public function testNamePrefixesANationalTypeWithN(): void
    {
        self::assertSame('NCHAR', (new Character(CharacterKind::Char, '3', true))->name());
        self::assertSame('NVARCHAR', (new Character(CharacterKind::VarChar, '3', true))->name());
    }

    public function testRenderWritesTheKeywordsTheLengthAndTheCharacterSetAttribute(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Character(CharacterKind::CharVarying, '010', false, new CharsetAttribute(CharsetForm::Named, new Name('latin1'), BinaryMark::Leading)))->render($out);

        self::assertSame('CHAR VARYING(010) BINARY CHARSET latin1', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesANationalTypeWithItsBinaryAttribute(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Character(CharacterKind::VarChar, '3', true, new CharsetAttribute(CharsetForm::Binary)))->render($out);

        self::assertSame('NVARCHAR(3) BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordsAloneWithoutLengthAndAttribute(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Character(CharacterKind::LongCharVarying))->render($out);

        self::assertSame('LONG CHAR VARYING', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesNcharForEverySpellingOfTheNationalChar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $national = $lowering->types->type($parser->parse('CREATE TABLE t (c NATIONAL CHAR(3) BINARY)')->find('type')[0]);
        $character = $lowering->types->type($parser->parse('CREATE TABLE t (c NATIONAL CHARACTER(3))')->find('type')[0]);
        $nchar = $lowering->types->type($parser->parse('CREATE TABLE t (c NCHAR)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $national);
        self::assertInstanceOf(Character::class, $character);
        self::assertInstanceOf(Character::class, $nchar);
        self::assertSame([CharacterKind::Char, '3', true], [$national->kind, $national->length, $national->national]);
        self::assertSame([CharacterKind::Char, '3', true, null], [$character->kind, $character->length, $character->national, $character->charset]);
        self::assertSame([CharacterKind::Char, null, true], [$nchar->kind, $nchar->length, $nchar->national]);
        self::assertSame(CharsetForm::Binary, $national->charset?->form);
        $national->render($out);
        self::assertSame('NCHAR(3) BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesNvarcharForEverySpellingOfTheNationalVarchar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $varying = $lowering->types->type($parser->parse('CREATE TABLE t (c NCHAR VARYING(3))')->find('type')[0]);
        $nvarchar = $lowering->types->type($parser->parse('CREATE TABLE t (c NVARCHAR(3))')->find('type')[0]);
        $national = $lowering->types->type($parser->parse('CREATE TABLE t (c NATIONAL CHAR VARYING(3))')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $varying);
        self::assertInstanceOf(Character::class, $nvarchar);
        self::assertInstanceOf(Character::class, $national);
        self::assertSame([CharacterKind::VarChar, '3', true], [$varying->kind, $varying->length, $varying->national]);
        self::assertSame([CharacterKind::VarChar, '3', true], [$nvarchar->kind, $nvarchar->length, $nvarchar->national]);
        self::assertSame([CharacterKind::VarChar, '3', true], [$national->kind, $national->length, $national->national]);
        $varying->render($out);
        self::assertSame('NVARCHAR(3)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderKeepsCharVaryingAndTheLongFormsApartFromVarchar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $varying = $lowering->types->type($parser->parse('CREATE TABLE t (c CHARACTER VARYING(10))')->find('type')[0]);
        $long = $lowering->types->type($parser->parse('CREATE TABLE t (c LONG)')->find('type')[0]);
        $longVarchar = $lowering->types->type($parser->parse('CREATE TABLE t (c LONG VARCHAR)')->find('type')[0]);
        $longVarying = $lowering->types->type($parser->parse('CREATE TABLE t (c LONG CHARACTER VARYING)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $varying);
        self::assertInstanceOf(Character::class, $long);
        self::assertInstanceOf(Character::class, $longVarchar);
        self::assertInstanceOf(Character::class, $longVarying);
        self::assertSame([CharacterKind::CharVarying, '10'], [$varying->kind, $varying->length]);
        self::assertSame(CharacterKind::Long, $long->kind);
        self::assertSame(CharacterKind::LongVarChar, $longVarchar->kind);
        self::assertSame(CharacterKind::LongCharVarying, $longVarying->kind);
        $longVarying->render($out);
        self::assertSame('LONG CHAR VARYING', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesATextWithLengthAndCharacterSet(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c TEXT(100) CHARACTER SET utf8mb4)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $type);
        self::assertSame([CharacterKind::Text, '100', false], [$type->kind, $type->length, $type->national]);
        self::assertSame('utf8mb4', $type->charset?->charset?->value);
        $type->render($out);
        self::assertSame('TEXT(100) CHARACTER SET utf8mb4', (new Lexical())->join($out->pieces()));
    }

    public function testNegativeLengthIsRejected(): void
    {
        $this->expectExceptionMessage('A length is an unsigned number.');

        new Character(CharacterKind::VarChar, '-1');
    }

    public function testNationalTextIsRejected(): void
    {
        $this->expectExceptionMessage('Only CHAR and VARCHAR have a national form.');

        new Character(CharacterKind::Text, null, true);
    }

    public function testNationalCharVaryingIsRejected(): void
    {
        $this->expectExceptionMessage('Only CHAR and VARCHAR have a national form.');

        new Character(CharacterKind::CharVarying, '3', true);
    }

    public function testNamedCharacterSetOnANationalTypeIsRejected(): void
    {
        $this->expectExceptionMessage('A national character type takes the BINARY attribute alone.');

        new Character(CharacterKind::Char, '3', true, new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4')));
    }

    public function testAsciiOnANationalTypeIsRejected(): void
    {
        $this->expectExceptionMessage('A national character type takes the BINARY attribute alone.');

        new Character(CharacterKind::VarChar, '3', true, new CharsetAttribute(CharsetForm::Ascii));
    }
}
