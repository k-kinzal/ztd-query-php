<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\ParameterSyntax;
use SqlSemantics\Core\Parameters;

#[CoversClass(Parameters::class)]
#[Small]
final class ParametersTest extends TestCase
{
    public function testSyntaxNamesTheLexerSyntaxOfEachChoice(): void
    {
        self::assertSame(ParameterSyntax::Native, Parameters::Native->syntax());
        self::assertSame(ParameterSyntax::Pdo, Parameters::Pdo->syntax());
        self::assertCount(2, Parameters::cases());
    }
}
