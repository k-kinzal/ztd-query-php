<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolForm;

#[CoversClass(BisonSymbolForm::class)]
final class BisonSymbolFormTest extends TestCase
{
    public function testIdentifierCase(): void
    {
        self::assertSame('Identifier', BisonSymbolForm::Identifier->name);
    }

    public function testCharLiteralCase(): void
    {
        self::assertSame('CharLiteral', BisonSymbolForm::CharLiteral->name);
    }
}
