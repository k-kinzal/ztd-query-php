<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(SelectionReader::class)]
final class SelectionReaderTest extends TestCase
{
    public function testTokenIndexAtOrAfter(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('t PARTITION (p0)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new SelectionReader();
        self::assertSame(1, $reader->tokenIndexAtOrAfter($tokens, 1));
        self::assertSame(count($tokens), $reader->tokenIndexAtOrAfter($tokens, 999));
    }

    public function testClosingParenthesisIndex(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('(p0, (p1))', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new SelectionReader();
        self::assertSame(6, $reader->closingParenthesisIndex($tokens, $tokens[0]));
        self::assertNull($reader->closingParenthesisIndex([$tokens[0]], $tokens[0]));
    }

    public function testPartitionNames(): void
    {
        $sql = '(`p0`, p1)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(['p0', 'p1'], (new SelectionReader())->partitionNames($sql, $tokens[0], $tokens[4]));
    }

    public function testHasAlias(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('a WHERE `JOIN` +', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new SelectionReader();
        self::assertTrue($reader->hasAlias($tokens, 0));
        self::assertFalse($reader->hasAlias($tokens, 1));
        self::assertTrue($reader->hasAlias($tokens, 2));
        self::assertFalse($reader->hasAlias($tokens, 3));
        self::assertFalse($reader->hasAlias($tokens, 4));
    }

    public function testPartitionSelection(): void
    {
        $sql = 't PARTITION (p0, p1)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(['names' => ['p0', 'p1'], 'closeIndex' => 6], (new SelectionReader())->partitionSelection($sql, $tokens, 1));
    }

    public function testRequireNoIndexHint(): void
    {
        $sql = 't PARTITION (p0) USE INDEX (idx)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        $this->expectExceptionMessage('index hint');
        (new SelectionReader())->requireNoIndexHint($sql, $tokens, 4);
    }

}
