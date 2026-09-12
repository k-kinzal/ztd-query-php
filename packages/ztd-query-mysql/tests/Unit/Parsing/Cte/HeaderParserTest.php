<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Cte;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(HeaderParser::class)]
final class HeaderParserTest extends TestCase
{
    public function testParseHeaderExtractsNamesAndStatementOffset(): void
    {
        $reader = new HeaderParser(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame(['names' => ['a'], 'statementOffset' => 21], $reader->parseHeader('WITH a AS (SELECT 1) SELECT * FROM a'));
        self::assertSame(['names' => [], 'statementOffset' => null], $reader->parseHeader('SELECT 1'));
        self::assertSame(['names' => [], 'statementOffset' => null], $reader->parseHeader('WITH a AS ('));
    }

    public function testFindAsIndexSkipsColumnListsAndStopsAtWords(): void
    {
        $reader = new HeaderParser(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame(2, $reader->findAsIndex(\ZtdQuery\Sql\SqlTokenStream::tokenize('() AS', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 0));
        self::assertNull($reader->findAsIndex(\ZtdQuery\Sql\SqlTokenStream::tokenize('other AS', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 0));
        self::assertNull($reader->findAsIndex([], 0));
    }

    public function testIdentifierNameUnquotesNamesAndRejectsSymbols(): void
    {
        $reader = new HeaderParser(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('plain `a``b` ,', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame('plain', $reader->identifierName($tokens[0]));
        self::assertSame('a`b', $reader->identifierName($tokens[1]));
        self::assertNull($reader->identifierName($tokens[2]));
    }

    public function testIsSymbolRequiresTheRequestedDelimiter(): void
    {
        $reader = new HeaderParser(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('( )', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertTrue($reader->isSymbol($tokens[0], '('));
        self::assertFalse($reader->isSymbol($tokens[0], ')'));
        self::assertFalse($reader->isSymbol(null, '('));
    }

    public function testTopLevelTokensExcludeNestedBodies(): void
    {
        $reader = new HeaderParser(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = $reader->topLevelTokens('WITH a AS (SELECT 1) SELECT 2');
        self::assertSame(['WITH', 'a', 'AS', '(', ')', 'SELECT', '2'], array_map(static fn (\ZtdQuery\Sql\SqlToken $token): string => $token->text, $tokens));
    }

    public function testContentsPreservesRecursiveHeaders(): void
    {
        $reader = new HeaderParser(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame(['leading' => '', 'recursive' => true, 'body' => 'a AS (SELECT 1)'], $reader->contents('WITH RECURSIVE a AS (SELECT 1) SELECT * FROM a', 31));
    }

}
