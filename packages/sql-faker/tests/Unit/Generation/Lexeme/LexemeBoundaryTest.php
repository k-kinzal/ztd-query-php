<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Token\TerminalOccurrence;

#[CoversClass(LexemeBoundary::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(TerminalOccurrence::class)]
final class LexemeBoundaryTest extends TestCase
{
    public function testPreservesDirectionAcrossACompoundBoundary(): void
    {
        $origin = new TerminalOccurrence('WITH_ROLLUP', 0);
        $left = new Lexeme('WITH', 'keyword', $origin, 'source', 'phrase');
        $right = new Lexeme('ROLLUP', 'keyword', $origin, 'source', 'phrase');
        $boundary = new LexemeBoundary($left, $right);
        self::assertSame($left, $boundary->left);
        self::assertSame($right, $boundary->right);
    }
}
