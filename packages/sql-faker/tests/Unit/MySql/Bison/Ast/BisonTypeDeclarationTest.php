<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTypeDeclaration;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonTypeDeclaration::class)]
final class BisonTypeDeclarationTest extends TestCase
{
    public function testTypeTag(): void
    {
        $decl = new BisonTypeDeclaration('<item>', ['expr', 'literal']);

        self::assertSame('<item>', $decl->typeTag);
    }

    public function testSymbols(): void
    {
        $decl = new BisonTypeDeclaration('<item>', ['expr', 'literal']);

        self::assertSame(['expr', 'literal'], $decl->symbols);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonTypeDeclaration('<t>', []));
    }
}
