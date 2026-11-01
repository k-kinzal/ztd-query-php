<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Text\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Text\Json\Failure;
use SqlSemantics\Platform\MySql\Rules\Text\Json\Syntax;

#[CoversClass(Syntax::class)]
#[CoversClass(Failure::class)]
#[Small]
final class SyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerMalformed(): iterable
    {
        yield 'empty document' => ['', 'The document is empty.', 0];
        yield 'invalid value' => ['bad', 'Invalid value.', 0];
        yield 'missing name' => ['{', 'Missing a name for object member.', 1];
        yield 'missing colon' => ['{"a" 1}', 'Missing a colon after a name of object member.', 5];
        yield 'missing object comma' => ['{"a":1 "b":2}', "Missing a comma or '}' after an object member.", 7];
        yield 'missing element' => ['[', 'Invalid value.', 1];
        yield 'missing array comma' => ['[1 2]', "Missing a comma or ']' after an array element.", 3];
        yield 'root suffix' => ['01', 'The document root must not be followed by other values.', 1];
        yield 'missing integer' => ['-x', 'Invalid value.', 1];
        yield 'missing fraction' => ['1.', 'Miss fraction part in number.', 2];
        yield 'missing exponent' => ['1e', 'Miss exponent in number.', 2];
        yield 'overflow' => ['1e309', 'Number too big to be stored in double.', 0];
        yield 'invalid literal' => ['tru', 'Invalid value.', 3];
        yield 'unclosed string' => ['"x', 'Missing a closing quotation mark in string.', 2];
        yield 'control character' => ["\"a\n\"", 'Invalid encoding in string.', 2];
        yield 'invalid escape' => ['"\x"', 'Invalid escape character in string.', 1];
        yield 'invalid hex' => ['"\u00zz"', 'Incorrect hex digit after \u escape in string.', 1];
        yield 'missing low surrogate' => ['"\uD800"', 'The surrogate pair in string is invalid.', 1];
        yield 'isolated low surrogate' => ['"\uDC00"', 'The surrogate pair in string is invalid.', 1];
    }

    #[DataProvider('providerMalformed')]
    public function testCheckIdentifiesDecodedByteBoundaries(string $text, string $reason, int $position): void
    {
        try {
            (new Syntax($text))->check();
            self::fail('A malformed document must not become an engine attribute.');
        } catch (Failure $failure) {
            self::assertSame($reason, $failure->reason);
            self::assertSame($position, $failure->position);
            self::assertFalse($failure->deep);
        }
    }

    public function testSpaceSkipsOnlyJsonWhitespace(): void
    {
        $syntax = new Syntax(" \t\r\n\v");
        $syntax->space();
        self::assertSame(4, $syntax->at);
    }

    public function testValueAcceptsEveryKindOfJsonValue(): void
    {
        $syntax = new Syntax('[{},[],"x",true,false,null,-1.25e+2]');
        $syntax->value();
        self::assertSame(strlen($syntax->text), $syntax->at);
        self::assertSame(0, $syntax->depth);
    }

    public function testEnterRefusesTheContainerBeyondTheDepthBoundary(): void
    {
        $syntax = new Syntax(str_repeat('[', 101) . '0' . str_repeat(']', 101));
        try {
            $syntax->check();
            self::fail('A document may nest at most 100 containers.');
        } catch (Failure $failure) {
            self::assertTrue($failure->deep);
            self::assertSame(101, $failure->position);
            self::assertSame('Terminate parsing due to Handler error.', $failure->reason);
        }
    }

    public function testObjectAcceptsRepeatedNamesAndNestedMembers(): void
    {
        $syntax = new Syntax('{"x":1,"x":{"y":[]}}');
        $syntax->object();
        self::assertSame(strlen($syntax->text), $syntax->at);
        self::assertSame(0, $syntax->depth);
    }

    public function testArrayAcceptsTheHundredthContainer(): void
    {
        $syntax = new Syntax(str_repeat('[', 100) . '0' . str_repeat(']', 100));
        $syntax->array();
        self::assertSame(strlen($syntax->text), $syntax->at);
        self::assertSame(0, $syntax->depth);
    }

    public function testLiteralStopsBeforeTheFollowingDocumentBytes(): void
    {
        $syntax = new Syntax('true,false');
        $syntax->literal('true');
        self::assertSame(4, $syntax->at);
    }

    public function testStringAcceptsUnicodeAndSurrogatePairs(): void
    {
        $syntax = new Syntax('"猫\uD800\uDC00"');
        $syntax->string();
        self::assertSame(strlen($syntax->text), $syntax->at);
    }

    public function testEscapeAcceptsEscapedBackslashes(): void
    {
        $syntax = new Syntax('\\\\');
        $syntax->escape();
        self::assertSame(2, $syntax->at);
    }

    public function testHexReadsAUnicodeEscapeAsOneCodeUnit(): void
    {
        $syntax = new Syntax('\u732b');
        self::assertSame(0x732b, $syntax->hex(0));
        self::assertSame(6, $syntax->at);
    }

    public function testNumberAllowsFiniteUnderflowWithoutIntegerOverflow(): void
    {
        $syntax = new Syntax('1e-9999999999999999999999999999');
        $syntax->number();
        self::assertSame(strlen($syntax->text), $syntax->at);
    }

    public function testExponentPreservesTheSignOfUnboundedMagnitudes(): void
    {
        $syntax = new Syntax('e-9999999999999999999999999999');
        self::assertLessThan(-308, $syntax->exponent());
        self::assertSame(strlen($syntax->text), $syntax->at);
    }
}
