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
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CharsetAttribute::class)]
#[Small]
final class CharsetAttributeTest extends TestCase
{
    public function testBinaryIsTrueForTheBinaryFormAlone(): void
    {
        self::assertTrue((new CharsetAttribute(CharsetForm::Binary))->binary());
        self::assertFalse((new CharsetAttribute(CharsetForm::Byte))->binary());
        self::assertFalse((new CharsetAttribute(CharsetForm::Ascii))->binary());
        self::assertFalse((new CharsetAttribute(CharsetForm::Unicode))->binary());
        self::assertFalse((new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4')))->binary());
    }

    public function testBinaryIsTrueWhereverTheMarkIsWritten(): void
    {
        self::assertTrue((new CharsetAttribute(CharsetForm::Ascii, null, BinaryMark::Leading))->binary());
        self::assertTrue((new CharsetAttribute(CharsetForm::Unicode, null, BinaryMark::Trailing))->binary());
        self::assertTrue((new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'), BinaryMark::Leading))->binary());
        self::assertTrue((new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'), BinaryMark::Trailing))->binary());
    }

    public function testRenderWritesTheShorthandForms(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        $out->list([new CharsetAttribute(CharsetForm::Ascii), new CharsetAttribute(CharsetForm::Unicode), new CharsetAttribute(CharsetForm::Byte), new CharsetAttribute(CharsetForm::Binary)]);

        self::assertSame('ASCII, UNICODE, BYTE, BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesBinaryBeforeOrAfterTheCharacterSetAsMarked(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        $out->list([
            new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'), BinaryMark::Leading),
            new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'), BinaryMark::Trailing),
            new CharsetAttribute(CharsetForm::Ascii, null, BinaryMark::Leading),
            new CharsetAttribute(CharsetForm::Unicode, null, BinaryMark::Trailing),
        ]);

        self::assertSame('BINARY CHARSET utf8mb4, CHARSET utf8mb4 BINARY, BINARY ASCII, UNICODE BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesACharacterSetNameThatIsAKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new CharsetAttribute(CharsetForm::Named, new Name('binary')))->render($out);

        self::assertSame('CHARSET `binary`', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesEveryFormLoweredFromADeclaration(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $leading = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) BINARY CHARACTER SET utf8mb4)')->find('type')[0]);
        $trailing = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) CHARSET utf8mb4 BINARY)')->find('type')[0]);
        $byte = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) BYTE)')->find('type')[0]);
        $binary = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) BINARY)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $leading);
        self::assertInstanceOf(Character::class, $trailing);
        self::assertInstanceOf(Character::class, $byte);
        self::assertInstanceOf(Character::class, $binary);
        self::assertInstanceOf(CharsetAttribute::class, $leading->charset);
        self::assertInstanceOf(CharsetAttribute::class, $trailing->charset);
        self::assertInstanceOf(CharsetAttribute::class, $byte->charset);
        self::assertInstanceOf(CharsetAttribute::class, $binary->charset);
        self::assertSame([CharsetForm::CharacterSet, 'utf8mb4', BinaryMark::Leading], [$leading->charset->form, $leading->charset->charset?->value, $leading->charset->mark]);
        self::assertSame([CharsetForm::Named, 'utf8mb4', BinaryMark::Trailing], [$trailing->charset->form, $trailing->charset->charset?->value, $trailing->charset->mark]);
        self::assertSame([CharsetForm::Byte, null, BinaryMark::Absent], [$byte->charset->form, $byte->charset->charset, $byte->charset->mark]);
        self::assertSame([CharsetForm::Binary, null, BinaryMark::Absent], [$binary->charset->form, $binary->charset->charset, $binary->charset->mark]);
        $out->list([$leading->charset, $trailing->charset, $byte->charset, $binary->charset]);
        self::assertSame('BINARY CHARACTER SET utf8mb4, CHARSET utf8mb4 BINARY, BYTE, BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesAsciiAndUnicodeWithTheirMarkUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $ascii = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) BINARY ASCII)')->find('type')[0]);
        $unicode = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) UNICODE BINARY)')->find('type')[0]);
        $plain = $lowering->types->type($parser->parse('CREATE TABLE t (c CHAR(3) ASCII)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $ascii);
        self::assertInstanceOf(Character::class, $unicode);
        self::assertInstanceOf(Character::class, $plain);
        self::assertInstanceOf(CharsetAttribute::class, $ascii->charset);
        self::assertInstanceOf(CharsetAttribute::class, $unicode->charset);
        self::assertInstanceOf(CharsetAttribute::class, $plain->charset);
        self::assertSame([CharsetForm::Ascii, BinaryMark::Leading, true], [$ascii->charset->form, $ascii->charset->mark, $ascii->charset->binary()]);
        self::assertSame([CharsetForm::Unicode, BinaryMark::Trailing, true], [$unicode->charset->form, $unicode->charset->mark, $unicode->charset->binary()]);
        self::assertSame([CharsetForm::Ascii, BinaryMark::Absent, false], [$plain->charset->form, $plain->charset->mark, $plain->charset->binary()]);
        $out->list([$ascii->charset, $unicode->charset, $plain->charset]);
        self::assertSame('BINARY ASCII, UNICODE BINARY, ASCII', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesANamedCharacterSetUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c TEXT CHARSET latin1)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Character::class, $type);
        self::assertInstanceOf(CharsetAttribute::class, $type->charset);
        self::assertSame('latin1', $type->charset->charset?->value);
        self::assertFalse($type->charset->binary());
        $type->charset->render($out);
        self::assertSame('CHARSET latin1', (new Lexical())->join($out->pieces()));
    }

    public function testNamedFormWithoutANameIsRejected(): void
    {
        $this->expectExceptionMessage('Exactly the named forms hold a character set name.');

        new CharsetAttribute(CharsetForm::Named);
    }

    public function testNameOnAShorthandFormIsRejected(): void
    {
        $this->expectExceptionMessage('Exactly the named forms hold a character set name.');

        new CharsetAttribute(CharsetForm::Ascii, new Name('latin1'));
    }

    public function testMarkOnTheByteFormIsRejected(): void
    {
        $this->expectExceptionMessage('BINARY accompanies ASCII, UNICODE or a named character set only.');

        new CharsetAttribute(CharsetForm::Byte, null, BinaryMark::Trailing);
    }

    public function testMarkOnTheBinaryFormIsRejected(): void
    {
        $this->expectExceptionMessage('BINARY accompanies ASCII, UNICODE or a named character set only.');

        new CharsetAttribute(CharsetForm::Binary, null, BinaryMark::Leading);
    }
}
