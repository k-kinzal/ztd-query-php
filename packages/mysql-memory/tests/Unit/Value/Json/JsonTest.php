<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
#[Small]
final class JsonTest extends TestCase
{
    public function testCanonicalWritesSpacesAfterCommasAndColons(): void
    {
        self::assertSame(['{"a": 1, "b": [1.0, "x/y"]}', '{"a": {}, "b": [], "c": [[]], "d": [{}]}', '[true, false, null]', '"str"', '123'], [Json::canonical('{"a":1,"b":[1.0,"x\/y"]}'), Json::canonical('{"a":{}, "b":[], "c":[[]], "d":[{}]}'), Json::canonical('[true,false,null]'), Json::canonical('"str"'), Json::canonical(' 123 ')]);
    }

    public function testCanonicalOrdersMembersByTheLengthOfTheirNamesKeepingTheLastOfOneName(): void
    {
        self::assertSame(['{"a": 2, "b": 1, "c": {"y": [], "z": 1}, "aa": 3}', '{"a": 2}', '{"100": 2, "1e2": 1}'], [Json::canonical('{"b":1,"a":2,"aa":3,"c":{"z":1,"y":[]}}'), Json::canonical('{"a":1,"a":2}'), Json::canonical('{"1e2":1,"100":2}')]);
    }

    public function testCanonicalWritesIntegersAndDoublesAsTheServerDoes(): void
    {
        self::assertSame('[1.0, 100.0, 0.0015, 0, -0.0, 12345678901234567890, 9223372036854775808, -9223372036854775808, -9.223372036854776e18, 1.8446744073709552e19, 1e15, 1234567890123456.8, 0.0000001, 1e-16]', Json::canonical('[1.0, 1e2, 1.5e-3, -0, -0.0, 12345678901234567890, 9223372036854775808, -9223372036854775808, -9223372036854775809, 18446744073709551616, 1e15, 1234567890123456.7, 1e-7, 1e-16]'));
    }

    public function testCanonicalWritesTheEscapesTheServerWrites(): void
    {
        self::assertSame('["é", "\u0001", "/", "\b\f\n\r\t", "\"", "\\\\", "😀", "' . "\x7f" . '", "\u0000"]', Json::canonical('["é", "\u0001", "\/", "\b\f\n\r\t", "\"", "\\\\", "😀", "\u007f", "\u0000"]'));
    }

    public function testCanonicalRefusesAnEmptyDocument(): void
    {
        $this->expectExceptionObject(new JsonSyntax('The document is empty.', 1));

        Json::canonical(' ');
    }

    public function testCanonicalRefusesTextAfterTheDocument(): void
    {
        $this->expectExceptionObject(new JsonSyntax('The document root must not be followed by other values.', 4));

        Json::canonical('[1] x');
    }

    public function testSpaceSkipsSpacesTabsAndLineBreaks(): void
    {
        $json = new Json(" \t\r\n1");
        $json->space();

        self::assertSame(4, $json->at);
    }

    public function testValueRefusesATextThatStartsNoValue(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid value.', 0));

        (new Json("'a'"))->value();
    }

    public function testEnterRefusesTheHundredAndFirstNestedContainer(): void
    {
        $json = new Json('[[');
        $json->depth = Json::MAX_DEPTH;

        $this->expectExceptionObject(new JsonSyntax('Terminate parsing due to Handler error.', 1, true));

        $json->enter();
    }

    public function testObjectRefusesAMemberWithoutAName(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Missing a name for object member.', 7));

        (new Json('{"a":1,}'))->object();
    }

    public function testObjectRefusesANameWithoutAColon(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Missing a colon after a name of object member.', 5));

        (new Json('{"a" 1}'))->object();
    }

    public function testObjectRefusesAMemberWithoutACommaOrBrace(): void
    {
        $this->expectExceptionObject(new JsonSyntax("Missing a comma or '}' after an object member.", 6));

        (new Json('{"a":1]'))->object();
    }

    public function testArrayRefusesAnElementWithoutACommaOrBracket(): void
    {
        $this->expectExceptionObject(new JsonSyntax("Missing a comma or ']' after an array element.", 3));

        (new Json('[1 2]'))->array();
    }

    public function testArrayRefusesAMissingElement(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid value.', 3));

        (new Json('[1,]'))->array();
    }

    public function testLiteralReportsTheFirstWrongCharacter(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid value.', 3));

        (new Json('tru'))->literal('true');
    }

    public function testStringRefusesAnUnterminatedString(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Missing a closing quotation mark in string.', 4));

        (new Json('"abc'))->string();
    }

    public function testStringRefusesAControlCharacter(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid encoding in string.', 4));

        (new Json("\"tab\tin\""))->string();
    }

    public function testEscapeRefusesAnUnknownEscape(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid escape character in string.', 0));

        (new Json('\x'))->escape();
    }

    public function testEscapeRefusesALoneSurrogate(): void
    {
        $this->expectExceptionObject(new JsonSyntax('The surrogate pair in string is invalid.', 0));

        (new Json('\ud800A'))->escape();
    }

    public function testHexRefusesADigitThatIsNotHexadecimal(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Incorrect hex digit after \u escape in string.', 0));

        (new Json('\u12G4'))->hex(0);
    }

    public function testDoubleWritesAnExponentPastFifteenIntegerDigitsOrFourteenLeadingZeros(): void
    {
        self::assertSame(['100000000000000.0', '1e15', '1.5e16', '1234567890123456.8', '0.000000000000001', '1.5e-16', '1e308', '-0.0'], [Json::double(1e14), Json::double(1e15), Json::double(1.5e16), Json::double(1234567890123456.7), Json::double(1e-15), Json::double(1.5e-16), Json::double(1e308), Json::double(-0.0)]);
    }

    public function testQuoteEscapesQuotesBackslashesAndControlCharacters(): void
    {
        self::assertSame('"a\"b\\\\c\n\u001f/é"', Json::quote("a\"b\\c\n\x1f/é"));
    }

    public function testVisibleDropsTheTypedText(): void
    {
        self::assertSame(['[1.50]', '[1]'], [Json::visible("[1.50]\0[`d1.50]"), Json::visible('[1]')]);
    }

    public function testCanonicalReadsADeeperDocumentUnderAHigherLimit(): void
    {
        self::assertSame(str_repeat('[', 101) . str_repeat(']', 101), Json::canonical(str_repeat('[', 101) . str_repeat(']', 101), 200));
    }
}
