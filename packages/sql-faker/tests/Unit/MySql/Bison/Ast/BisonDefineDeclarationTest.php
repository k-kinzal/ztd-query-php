<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonDefineDeclaration;

#[CoversNothing]
final class BisonDefineDeclarationTest extends TestCase
{
    public function testName(): void
    {
        $decl = new BisonDefineDeclaration('api.pure', 'full');

        self::assertSame('api.pure', $decl->name);
    }

    public function testValue(): void
    {
        $decl = new BisonDefineDeclaration('api.pure', 'full');

        self::assertSame('full', $decl->value);
    }

    public function testValueNull(): void
    {
        $decl = new BisonDefineDeclaration('api.pure', null);

        self::assertNull($decl->value);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonDefineDeclaration('x', null));
    }
}
