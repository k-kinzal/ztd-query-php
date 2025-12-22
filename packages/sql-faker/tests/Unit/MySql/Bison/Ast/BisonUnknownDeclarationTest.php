<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonUnknownDeclaration::class)]
final class BisonUnknownDeclarationTest extends TestCase
{
    public function testDirective(): void
    {
        $decl = new BisonUnknownDeclaration('%custom', 'some content');

        self::assertSame('%custom', $decl->directive);
    }

    public function testContent(): void
    {
        $decl = new BisonUnknownDeclaration('%custom', 'some content');

        self::assertSame('some content', $decl->content);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonUnknownDeclaration('%x', ''));
    }
}
