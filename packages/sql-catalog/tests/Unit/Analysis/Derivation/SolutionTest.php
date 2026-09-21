<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Solution;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;

#[CoversClass(Solution::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
final class SolutionTest extends TestCase
{
    public function testASolutionHoldsOneValuePerExpressionAndHowItWasReached(): void
    {
        $solution = new Solution([Domain::literal('SELECT 1')], ['f', 'g'], true, true);

        self::assertSame('SELECT 1', $solution->values[0]->soleLiteral()?->value);
        self::assertSame(['f', 'g'], $solution->through);
        self::assertTrue($solution->truncated);
        self::assertTrue($solution->combined);
    }

    public function testASolutionIsNeitherCutShortNorPairedUnlessItSaysSo(): void
    {
        $solution = new Solution([], []);

        self::assertFalse($solution->truncated);
        self::assertFalse($solution->combined);
    }
}
