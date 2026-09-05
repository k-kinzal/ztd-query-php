<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;

#[CoversNothing]
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
}
