<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\ParameterSyntax;

#[CoversClass(ParameterSyntax::class)]
#[Small]
final class ParameterSyntaxTest extends TestCase
{
    public function testCases(): void
    {
        self::assertCount(2, ParameterSyntax::cases());
        self::assertSame('Native', ParameterSyntax::Native->name);
    }

    public function testNamedLengthReadsANamedPlaceholder(): void
    {
        self::assertSame(3, ParameterSyntax::Named->namedLength('SELECT :id, 1', 7));
        self::assertSame(8, ParameterSyntax::Named->namedLength(':user_1x)', 0));
        self::assertSame(0, ParameterSyntax::Named->namedLength(':=', 0));
        self::assertSame(0, ParameterSyntax::Named->namedLength(':', 0));
        self::assertSame(0, ParameterSyntax::Named->namedLength(': id', 0));
        self::assertSame(0, ParameterSyntax::Named->namedLength('x::int', 2));
        self::assertSame(0, ParameterSyntax::Named->namedLength('x::int', 1));
    }

    public function testNamedLengthIsZeroForTheNativeSyntax(): void
    {
        self::assertSame(0, ParameterSyntax::Native->namedLength(':id', 0));
    }
}
