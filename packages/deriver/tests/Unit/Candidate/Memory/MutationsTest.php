<?php

declare(strict_types=1);

namespace Tests\Unit\Candidate\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class MutationsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueDistinguishesPostfixExpressionAndStoredValue(): void
    {
        $engine = F::evaluator('function target(){$x=4;$old=$x++;return [$old,$x];}');
        self::assertSame([4, 5], F::value($engine)->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testIncrementSharesPhpStringCarrySemantics(): void
    {
        $engine = F::evaluator('function target(){$x="a9";$old=$x++;return [$old,$x];}');
        self::assertSame(['a9', 'b0'], F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testIncrementKeepsDiagnosticResultsAndTheirSource(): void
    {
        $engine = F::evaluator('function target(){$x=true;return ++$x;}');
        $value = F::value($engine);
        self::assertTrue($value->native());
        self::assertSame('PHP_WARNING', $value->attributes['reason']);
        self::assertSame('candidate.php', $value->attributes['source']);
    }

}
