<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class GuardsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testAtKeepsBothSymbolicBranchIdentities(): void
    {
        $e = F::evaluator('function target($x){if($x){return 1;}return 2;}');
        $r = $e->returns(F::frame($e), 64);
        self::assertCount(2, (new \Deriver\Evaluation\Candidate\Choices())->alternatives($r));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testEdgeRejectsAConcreteContradiction(): void
    {
        $e = F::evaluator('function target(){if(false){return missing();}return 2;}');
        self::assertSame(2, F::value($e)->native());
    }
}
