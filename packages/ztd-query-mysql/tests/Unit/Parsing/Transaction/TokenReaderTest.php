<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Transaction\TokenReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(TokenReader::class)]
final class TokenReaderTest extends TestCase
{
    public function testMatchesAnyAcceptsOnlyCompleteForms(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('START TRANSACTION', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reader = new TokenReader();
        self::assertTrue($reader->matchesAny($tokens, [['BEGIN'], ['START', 'TRANSACTION']]));
        self::assertFalse($reader->matchesAny($tokens, [['START']]));
    }

    public function testMatchesRejectsExtraTokensAndQuotedKeywords(): void
    {
        $reader = new TokenReader();
        self::assertTrue($reader->matches(\ZtdQuery\Sql\SqlTokenStream::tokenize('COMMIT WORK', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), ['COMMIT', 'WORK']));
        self::assertFalse($reader->matches(\ZtdQuery\Sql\SqlTokenStream::tokenize('COMMIT WORK', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), ['COMMIT']));
        self::assertFalse($reader->matches(\ZtdQuery\Sql\SqlTokenStream::tokenize('`COMMIT`', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), ['COMMIT']));
    }

    public function testNameAfterReadsSavepointNames(): void
    {
        $reader = new TokenReader();
        self::assertSame('a`b', $reader->nameAfter(\ZtdQuery\Sql\SqlTokenStream::tokenize('SAVEPOINT `a``b`', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), [['SAVEPOINT']]));
        self::assertNull($reader->nameAfter(\ZtdQuery\Sql\SqlTokenStream::tokenize('SAVEPOINT 42', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), [['SAVEPOINT']]));
        self::assertNull($reader->nameAfter([], [['SAVEPOINT']]));
    }

    public function testUnquoteRejectsUnclosedIdentifiers(): void
    {
        $reader = new TokenReader();
        self::assertSame('savepoint', $reader->unquote('savepoint', ['`', '"']));
        self::assertSame('a`b', $reader->unquote('`a``b`', ['`', '"']));
        self::assertNull($reader->unquote('`broken', ['`', '"']));
    }

}
