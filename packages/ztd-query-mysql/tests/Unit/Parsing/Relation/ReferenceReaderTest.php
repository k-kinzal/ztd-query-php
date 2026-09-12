<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(ReferenceReader::class)]
final class ReferenceReaderTest extends TestCase
{
    public function testReferencesFromClause(): void
    {
        self::assertSame([['name' => 'users', 'start' => 0, 'unqualifiedStart' => 3, 'end' => 8], ['name' => 'posts', 'start' => 16, 'unqualifiedStart' => 16, 'end' => 21]], (new ReferenceReader())->referencesFromClause('db.users u JOIN posts p ON p.uid = u.id'));
    }

    public function testClosingToken(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('(users)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new ReferenceReader();
        self::assertSame($tokens[2], $reader->closingToken($tokens, 0));
        self::assertNull($reader->closingToken([$tokens[0]], 0));
    }

    public function testReferenceAt(): void
    {
        $sql = '`db`.`users`';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(['name' => 'users', 'start' => 0, 'unqualifiedStart' => 5, 'end' => 12], (new ReferenceReader())->referenceAt($sql, $tokens, 0));
        self::assertNull((new ReferenceReader())->referenceAt('SELECT', \ZtdQuery\Sql\SqlTokenStream::tokenize('SELECT', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 0));
    }

    public function testIdentifierComponentAt(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('`a``b` +', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new ReferenceReader();
        self::assertSame(['a`b', 1, 0, 0, 6], $reader->identifierComponentAt($tokens, 0));
        self::assertNull($reader->identifierComponentAt($tokens, 1));
        self::assertNull($reader->identifierComponentAt($tokens, 2));
    }

    public function testFindFromEnd(): void
    {
        $sql = 'SELECT * FROM users WHERE id = 1';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(20, (new ReferenceReader())->findFromEnd($sql, $tokens, $tokens[2]));
    }

    public function testMatchesKeywordSequence(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('ORDER BY id', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new ReferenceReader();
        self::assertTrue($reader->matchesKeywordSequence($tokens, 0, ['ORDER', 'BY']));
        self::assertFalse($reader->matchesKeywordSequence($tokens, 0, ['GROUP', 'BY']));
        self::assertFalse($reader->matchesKeywordSequence($tokens, 2, ['ORDER', 'BY']));
    }

    public function testParenthesizedReferences(): void
    {
        $sql = '(db.users)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame([['name' => 'users', 'start' => 1, 'unqualifiedStart' => 4, 'end' => 9]], (new ReferenceReader())->parenthesizedReferences($sql, $tokens, 0));
        $sql = '(SELECT * FROM users)';
        self::assertSame([], (new ReferenceReader())->parenthesizedReferences($sql, \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 0));
    }

}
