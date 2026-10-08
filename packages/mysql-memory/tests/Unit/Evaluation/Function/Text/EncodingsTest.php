<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Encodings;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encodings::class)]
#[Small]
final class EncodingsTest extends TestCase
{
    public function testRoutinesNamesTheEncodingFunctions(): void
    {
        self::assertSame(['QUOTE', 'ORD', 'TO_BASE64', 'FROM_BASE64'], array_map(static fn ($routine): string => $routine->name, (new Encodings())->routines()));
    }

    public function testQuoteEscapesAsAStringLiteral(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT QUOTE('a''b\\\\c'), QUOTE(NULL), HEX(QUOTE(_binary 0x001A22)), QUOTE(1), CHARSET(QUOTE(1)), HEX(QUOTE(CONVERT('a''b' USING utf32)))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([["'a\\'b\\\\c'", 'NULL', '275C305C5A2227', "'1'", 'latin1', '00000027000000610000005C000000270000006200000027']], $result->rows);
    }

    public function testOrdAnswersTheBytesOfTheFirstCharacter(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ORD('é'), ORD('😀'), ORD(''), ORD(CONVERT('😀' USING utf16)), ORD(_binary'é'), ORD(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['50089', '4036991104', '0', '3627933184', '195', null]], $result->rows);
    }

    public function testToBase64BreaksLinesEach76Characters(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT TO_BASE64('abc'), HEX(SUBSTRING(TO_BASE64(REPEAT('a', 58)), 76, 3)), TO_BASE64(''), TO_BASE64(NULL), TO_BASE64(REPEAT('a', 58))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['YWJj', '680A59', '', null], array_slice($result->rows[0], 0, 4));
        self::assertSame(1264, $result->columns[4]->length);
    }

    public function testFromBase64DecodesOnlyAPaddedText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FROM_BASE64('Y W J j'), FROM_BASE64('YQ= ='), FROM_BASE64('YR=='), FROM_BASE64('YQ'), FROM_BASE64('YQ==YQ=='), FROM_BASE64('YWJj\\fYQ=='), FROM_BASE64('')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', 'a', 'a', null, null, null, '']], $result->rows);
    }
}
