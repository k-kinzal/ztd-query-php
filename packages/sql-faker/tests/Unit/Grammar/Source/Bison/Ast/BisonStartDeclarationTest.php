<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonStartDeclaration;

#[CoversClass(BisonStartDeclaration::class)]
final class BisonStartDeclarationTest extends TestCase
{
    public function testSymbol(): void
    {
        $decl = new BisonStartDeclaration('sql_statement');

        self::assertSame('sql_statement', $decl->symbol);
    }
}
