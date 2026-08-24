<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;

#[CoversClass(BisonLexeme::class)]
final class BisonLexemeTest extends TestCase
{
    public function testCasesCount(): void
    {
        self::assertCount(13, BisonLexeme::cases());
    }
}
