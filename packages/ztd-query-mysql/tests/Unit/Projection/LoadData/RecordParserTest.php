<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\LoadData\RecordParser;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\ColumnMapping::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\InputFormat::class)]
#[CoversClass(RecordParser::class)]
final class RecordParserTest extends TestCase
{
    public function testSplitRecords(): void
    {
        $format = new \ZtdQuery\Platform\MySql\Projection\LoadData\InputFormat(',', '"', '\\', '', "\n");
        self::assertSame(["1,\"two\nlines\"", '2,"a""b"', '3,tail'], (new RecordParser())->splitRecords("1,\"two\nlines\"\n2,\"a\"\"b\"\n3,tail", $format));
        self::assertSame([], (new RecordParser())->splitRecords('', $format));
    }

    public function testParseFields(): void
    {
        $format = new \ZtdQuery\Platform\MySql\Projection\LoadData\InputFormat(',', '"', '\\', '', "\n");
        self::assertSame(['1', 'a,b', 'a"b', "line\nfeed", null, 'NULL', ''], (new RecordParser())->parseFields('1,"a,b","a""b",line\\nfeed,\\N,"NULL",', $format));
    }

    public function testFieldValue(): void
    {
        $parser = new RecordParser();
        self::assertNull($parser->fieldValue('\\N', 'N', false, '', '\\'));
        self::assertSame('N', $parser->fieldValue('\\N', 'N', true, '"', '\\'));
        self::assertNull($parser->fieldValue('NULL', 'NULL', false, '"', '\\'));
        self::assertNull($parser->fieldValue('NULL', 'NULL', false, '', ''));
        self::assertSame('NULL', $parser->fieldValue('NULL', 'NULL', false, '', '\\'));
        self::assertSame('value', $parser->fieldValue('value', 'value', false, '', '\\'));
    }

    public function testDecodeEscapeCase1(): void
    {
        $parser = new RecordParser();
        $byte = '0';
        $expected = "\x00";
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testDecodeEscapeCase2(): void
    {
        $parser = new RecordParser();
        $byte = 'b';
        $expected = "\x08";
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testDecodeEscapeCase3(): void
    {
        $parser = new RecordParser();
        $byte = 'n';
        $expected = "\n";
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testDecodeEscapeCase4(): void
    {
        $parser = new RecordParser();
        $byte = 'r';
        $expected = "\r";
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testDecodeEscapeCase5(): void
    {
        $parser = new RecordParser();
        $byte = 't';
        $expected = "\t";
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testDecodeEscapeCase6(): void
    {
        $parser = new RecordParser();
        $byte = 'Z';
        $expected = "\x1a";
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testDecodeEscapeCase7(): void
    {
        $parser = new RecordParser();
        $byte = 'q';
        $expected = 'q';
        self::assertSame($expected, $parser->decodeEscape($byte));
    }

    public function testEnclosureWidth(): void
    {
        $parser = new RecordParser();
        self::assertSame(2, $parser->enclosureWidth('"', true, '"'));
        self::assertSame(1, $parser->enclosureWidth('"', false, '"'));
        self::assertSame(1, $parser->enclosureWidth(null, true, '"'));
        self::assertSame(1, $parser->enclosureWidth('x', true, '"'));
    }

}
