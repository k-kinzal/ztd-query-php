<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Lexeme;

#[CoversClass(Lexeme::class)]
#[Small]
final class LexemeTest extends TestCase
{
    public function testEnd(): void
    {
        $lexeme = new Lexeme('IDENT', 'users', 12);

        self::assertSame(17, $lexeme->end());
        self::assertSame('IDENT', $lexeme->name);
    }
}
