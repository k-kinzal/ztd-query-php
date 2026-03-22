<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonParamDeclaration;

#[CoversNothing]
final class BisonParamDeclarationTest extends TestCase
{
    public function testKind(): void
    {
        $decl = new BisonParamDeclaration('parse-param', '{ THD *thd }');

        self::assertSame('parse-param', $decl->kind);
    }

    public function testCode(): void
    {
        $decl = new BisonParamDeclaration('lex-param', '{ void *scanner }');

        self::assertSame('{ void *scanner }', $decl->code);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonParamDeclaration('parse-param', '{}'));
    }
}
