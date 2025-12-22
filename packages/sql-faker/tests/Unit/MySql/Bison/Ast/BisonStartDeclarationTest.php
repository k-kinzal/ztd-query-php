<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonStartDeclaration::class)]
final class BisonStartDeclarationTest extends TestCase
{
    public function testSymbol(): void
    {
        $decl = new BisonStartDeclaration('sql_statement');

        self::assertSame('sql_statement', $decl->symbol);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonStartDeclaration('start'));
    }
}
