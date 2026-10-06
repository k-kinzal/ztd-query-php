<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Text::class)]
#[Small]
final class TextTest extends TestCase
{
    public function testRenderWritesAQuotedStringUnderTheBackslashRule(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Text("it's"))->render($out);
        (new Text('a\\b'))->render($out);

        self::assertSame("'it''s' 'a\\\\b'", (new Lexical())->join($out->pieces()));
    }

    public function testRenderKeepsBackslashesUnderTheVerbatimRule(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Text('a\\b', EscapeRule::Verbatim))->render($out);

        self::assertSame("'a\\b'", (new Lexical())->join($out->pieces()));
    }

    public function testRenderSpellsHexadecimalDigitsByTheirCount(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Text('4142', EscapeRule::Backslash, Radix::Hexadecimal))->render($out);
        (new Text('A', EscapeRule::Backslash, Radix::Hexadecimal))->render($out);

        self::assertSame("x'4142' 0xA", (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesBitDigits(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Text('101', EscapeRule::Backslash, Radix::Bit))->render($out);
        (new Text('', EscapeRule::Backslash, Radix::Bit))->render($out);

        self::assertSame("b'101' b''", (new Lexical())->join($out->pieces()));
    }

    public function testLoweredEnumMembersHoldDecodedBytesOrDigits(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $nodes = $platform->parser($profile)->parse("CREATE TABLE t (c ENUM('a\\nb', X'41', 0b1))")->find('text_string');
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $quoted = $lowering->literals->text($nodes[0]);
        $hexadecimal = $lowering->literals->text($nodes[1]);
        $bit = $lowering->literals->text($nodes[2]);

        self::assertSame("a\nb", $quoted->value);
        self::assertNull($quoted->radix);
        self::assertSame(EscapeRule::Backslash, $quoted->escapes);
        self::assertSame('41', $hexadecimal->value);
        self::assertSame(Radix::Hexadecimal, $hexadecimal->radix);
        self::assertSame('1', $bit->value);
        self::assertSame(Radix::Bit, $bit->radix);
    }

    public function testLoweredTextFollowsTheEscapeRuleOfTheProfile(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', Mode::fromString('NO_BACKSLASH_ESCAPES'), ParameterStyle::Native);
        $node = $platform->parser($profile)->parse("CREATE TABLE t (c ENUM('a\\nb'))")->find('text_string')[0];
        $text = (new Lowering($platform->productions($profile), new Leaves(), $profile))->literals->text($node);
        $out = new Output($platform->codec($profile));
        $text->render($out);

        self::assertSame('a\\nb', $text->value);
        self::assertSame(EscapeRule::Verbatim, $text->escapes);
        self::assertSame("'a\\nb'", (new Lexical())->join($out->pieces()));
    }

    public function testRejectsNonHexadecimalDigits(): void
    {
        $this->expectExceptionMessage('A hexadecimal text holds hexadecimal digits.');

        new Text('G', EscapeRule::Backslash, Radix::Hexadecimal);
    }

    public function testRejectsNonBinaryDigits(): void
    {
        $this->expectExceptionMessage('A bit text holds binary digits.');

        new Text('2', EscapeRule::Backslash, Radix::Bit);
    }
}
