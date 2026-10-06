<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Control;

use Deriver\Evaluation\Candidate\Control\Dependencies as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class DependenciesTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testBuildDoesNotControlAJoinWithIrrelevantBranches(): void
    {
        $engine = F::evaluator('function target($b){if($b){$x=1;}else{$x=2;}return 42;}');
        $frame = F::frame($engine);
        $returns = $frame->graph->returns();
        self::assertSame([], (new Subject())->build($frame->graph)[$returns[0][0]] ?? []);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testPostdominatorsIncludesTheSingleExit(): void
    {
        $graph = F::frame(F::evaluator('function target(){return 42;}'))->graph;
        self::assertSame([-1 => -1,0 => -1], (new Subject())->postdominators($graph, null));
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testOrderStartsAtTheVirtualExit(): void
    {
        $graph = F::frame(F::evaluator('function target(){return 42;}'))->graph;
        self::assertSame([-1,0], (new Subject())->order($graph));
    }

    public function testIntersectFindsTheNearestSharedAncestor(): void
    {
        self::assertSame(2, (new Subject())->intersect(0, 1, [-1 => -1,2 => -1,0 => 2,1 => 2], [-1 => 0,2 => 1,0 => 2,1 => 3]));
    }

}
