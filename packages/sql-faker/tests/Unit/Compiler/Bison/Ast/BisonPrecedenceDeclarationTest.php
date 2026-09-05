<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonPrecedenceDeclaration;

#[CoversClass(BisonPrecedenceDeclaration::class)]
final class BisonPrecedenceDeclarationTest extends TestCase
{
    public function testAssociativity(): void
    {
        $decl = new BisonPrecedenceDeclaration('left', null, ['OR_SYM']);

        self::assertSame('left', $decl->associativity);
    }

    public function testTypeTag(): void
    {
        $decl = new BisonPrecedenceDeclaration('right', '<type>', ['UMINUS']);

        self::assertSame('<type>', $decl->typeTag);
    }

    public function testTypeTagNull(): void
    {
        $decl = new BisonPrecedenceDeclaration('nonassoc', null, []);

        self::assertNull($decl->typeTag);
    }

    public function testSymbols(): void
    {
        $decl = new BisonPrecedenceDeclaration('left', null, ['OR_SYM', 'OR2_SYM']);

        self::assertSame(['OR_SYM', 'OR2_SYM'], $decl->symbols);
    }
}
