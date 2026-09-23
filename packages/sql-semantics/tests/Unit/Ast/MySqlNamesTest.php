<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;

#[CoversClass(\SqlSemantics\Ast\MySqlNames::class)]
#[Medium]
final class MySqlNamesTest extends TestCase
{
    #[TestWith(["'one''two'", "one'two"]) ]
    #[TestWith(["'one\\'two'", "one'two"]) ]
    #[TestWith(["'a\\nb'", "a\nb"]) ]
    #[TestWith(["'a\\%b'", 'a\\%b']) ]
    public function testReadDecodesLiteralNamesInLexicalOrder(string $text, string $expected): void
    {
        $token = new \SqlParser\Lexer\Token(0, 'TEXT_STRING', $text, 0);
        self::assertSame($expected, \SqlSemantics\Ast\MySqlNames::read($token, new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }

    public function testReadPreservesBackslashesInQuotedIdentifiers(): void
    {
        $token = new \SqlParser\Lexer\Token(0, 'IDENT_QUOTED', '`a\\b``c`', 0);
        self::assertSame('a\\b`c', \SqlSemantics\Ast\MySqlNames::read($token, new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }
}
