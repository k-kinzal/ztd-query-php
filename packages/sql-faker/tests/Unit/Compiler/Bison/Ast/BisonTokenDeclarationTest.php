<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDefinition;

#[CoversClass(BisonTokenDeclaration::class)]
#[CoversClass(BisonTokenDefinition::class)]
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
        $token = new BisonTokenDefinition('SELECT', 123, '"SELECT"');
        $decl = new BisonTokenDeclaration(null, [$token]);

        self::assertSame([$token], $decl->tokens);
    }
}
