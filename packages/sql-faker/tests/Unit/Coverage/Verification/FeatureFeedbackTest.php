<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GenerationTrace;
use SqlFaker\Coverage\Verification\FeatureFeedback;

#[CoversClass(FeatureFeedback::class)]
#[UsesClass(GenerationTrace::class)]
final class FeatureFeedbackTest extends TestCase
{
    public function testEdgesAreStableAcrossObservationOrderAndSeparateVerdicts(): void
    {
        $trace = (new GenerationTrace(1, 'root', []))->value;
        $trace['features'] = ['lexeme' => ['alpha', 'beta'], 'rewrite' => ['repair']];
        $feedback = new FeatureFeedback();
        $first = $feedback->edges($trace);
        $trace['features'] = ['rewrite' => ['repair'], 'lexeme' => ['beta', 'alpha']];
        self::assertEquals($first, $feedback->edges($trace));
        self::assertSame([], array_filter(array_keys($first), static fn (int $edge): bool => $edge >= -1000000));
        $trace['emittedIds'] = ['production'];
        self::assertCount(4, $feedback->edges($trace, 'accepted'));
        self::assertSame([], array_intersect_key($feedback->edges($trace, 'accepted'), $feedback->edges($trace, 'semantic-inconclusive')));
    }
}
