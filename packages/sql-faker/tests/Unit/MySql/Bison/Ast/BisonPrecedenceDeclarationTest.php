<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonPrecedenceDeclaration;

#[CoversNothing]
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

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonPrecedenceDeclaration('left', null, []));
    }
}
