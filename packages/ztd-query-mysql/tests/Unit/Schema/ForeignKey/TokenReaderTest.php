<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Schema\ForeignKey\TokenReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(TokenReader::class)]
final class TokenReaderTest extends TestCase
{
    public function testIsSymbol(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('(id)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertTrue(TokenReader::isSymbol($tokens[0], '('));
        self::assertFalse(TokenReader::isSymbol($tokens[1], 'id'));
        self::assertFalse(TokenReader::isSymbol(null, '('));
        self::assertFalse(TokenReader::isSymbol($tokens[0], ')'));
    }

}
