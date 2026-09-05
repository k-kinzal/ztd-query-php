<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonTypeDeclaration;

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
}
