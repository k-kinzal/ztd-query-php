<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Upsert\LiteralReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(LiteralReader::class)]
final class LiteralReaderTest extends TestCase
{
    public function testIdentifierPreservesWordsAndUnquotesNames(): void
    {
        $reader = new LiteralReader();
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('score `a``b`', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame('score', $reader->identifier($tokens[0]));
        self::assertSame('a`b', $reader->identifier($tokens[1]));
    }

    public function testNumberReadsIntegerHexAndScientificForms(): void
    {
        $reader = new LiteralReader();
        self::assertSame(42, $reader->number('42'));
        self::assertSame(255, $reader->number('0xff'));
        self::assertSame(1.5, $reader->number('1.5'));
        self::assertSame(200.0, $reader->number('2e2'));
    }

    public function testNumberRejectsUnsupportedLiteral(): void
    {
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        (new LiteralReader())->number('1 + 2');
    }

}
