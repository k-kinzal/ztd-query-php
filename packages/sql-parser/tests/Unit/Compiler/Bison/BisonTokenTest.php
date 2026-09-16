<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonToken;
use SqlParser\Compiler\Bison\BisonTokenKind;

#[CoversClass(BisonToken::class)]
#[Small]
final class BisonTokenTest extends TestCase
{
    public function testIsDirective(): void
    {
        $token = new BisonToken(BisonTokenKind::Directive, 'prec', 3);

        self::assertTrue($token->isDirective('prec'));
        self::assertFalse($token->isDirective('left'));
        self::assertFalse((new BisonToken(BisonTokenKind::Identifier, 'prec', 3))->isDirective('prec'));
    }

    public function testIsSymbol(): void
    {
        self::assertTrue((new BisonToken(BisonTokenKind::Identifier, 'expr', 1))->isSymbol());
        self::assertTrue((new BisonToken(BisonTokenKind::CharLiteral, '(', 1))->isSymbol());
        self::assertFalse((new BisonToken(BisonTokenKind::String, 'x', 1))->isSymbol());
    }
}
