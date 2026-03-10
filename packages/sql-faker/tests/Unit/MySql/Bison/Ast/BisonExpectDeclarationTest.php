<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonExpectDeclaration;

#[CoversNothing]
final class BisonExpectDeclarationTest extends TestCase
{
    public function testCount(): void
    {
        $decl = new BisonExpectDeclaration(37);

        self::assertSame(37, $decl->count);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonExpectDeclaration(0));
    }
}
