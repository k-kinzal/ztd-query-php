<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonTokenType::class)]
final class BisonTokenTypeTest extends TestCase
{
    public function testCasesCount(): void
    {
        self::assertCount(13, BisonTokenType::cases());
    }
}
