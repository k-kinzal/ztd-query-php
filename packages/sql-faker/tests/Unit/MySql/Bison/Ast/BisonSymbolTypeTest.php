<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;

#[CoversNothing]
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
