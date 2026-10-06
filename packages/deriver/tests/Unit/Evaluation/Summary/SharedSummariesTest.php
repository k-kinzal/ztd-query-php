<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Summary;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class SharedSummariesTest extends TestCase
{
    public function testReplayMissesWithoutAnAdmittedClosedResult(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        self::assertNull($context->shared->replay('missing', $context, new \Deriver\Evaluation\State()));
    }

    public function testRememberRejectsInterruptedComputations(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Evaluation\State();
        $key = (new \Deriver\Evaluation\Summary\Invocation())->key($body, $state, []);
        $cell = new \Deriver\Evaluation\Demand\Cell($key, $body, $state);
        $cell->status = 'frontier';
        $context->shared->remember('interrupted', $context, $cell);
        self::assertNull($context->shared->replay('interrupted', $context, $state));
    }
}
