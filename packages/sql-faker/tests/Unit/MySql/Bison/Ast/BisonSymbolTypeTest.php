<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonSymbolType::class)]
final class BisonSymbolTypeTest extends TestCase
{
    public function testIdentifierCase(): void
    {
        self::assertSame('Identifier', BisonSymbolType::Identifier->name);
    }

    public function testCharLiteralCase(): void
    {
        self::assertSame('CharLiteral', BisonSymbolType::CharLiteral->name);
    }
}
