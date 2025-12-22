<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenInfo;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonTokenDeclaration::class)]
#[CoversClass(BisonTokenInfo::class)]
final class BisonTokenDeclarationTest extends TestCase
{
    public function testTypeTag(): void
    {
        $decl = new BisonTokenDeclaration('<lexer.keyword>', []);

        self::assertSame('<lexer.keyword>', $decl->typeTag);
    }

    public function testTypeTagNull(): void
    {
        $decl = new BisonTokenDeclaration(null, []);

        self::assertNull($decl->typeTag);
    }

    public function testTokens(): void
    {
        $token = new BisonTokenInfo('SELECT', 123, '"SELECT"');
        $decl = new BisonTokenDeclaration(null, [$token]);

        self::assertSame([$token], $decl->tokens);
    }

    public function testImplementsBisonDeclaration(): void
    {
        self::assertInstanceOf(BisonDeclaration::class, new BisonTokenDeclaration(null, []));
    }
}
