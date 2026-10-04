<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class QueryExecutionTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testDeriveUsesTheCandidateContractByDefault(): void
    {
        $r = C::argument(C::session('function target(){observe(1);}'));
        self::assertSame('candidates', $r->contract);
        self::assertSame('not-assessed', $r->reachability);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testFramesKeepExplicitBindingsOutOfOtherQueries(): void
    {
        $s = C::session('function target($x){observe($x);}');
        $site = $s->callsTo('observe')[0];
        $scope = \Deriver\Query\QueryScope::fromEntrypoints([new \Deriver\Project\EntryPoint('target', [Term::constant(3)])]);
        $r = $s->derive(new \Deriver\Query\ValueQuery($site->argument(0), scope:$scope));
        self::assertSame([3], C::native($r));
        self::assertNotEmpty(C::argument($s)->frontiers);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testObserveProjectsADemandedArrayEntry(): void
    {
        $s = C::session('function target(){observe([1,2]);}');
        $r = $s->derive(new \Deriver\Query\ValueQuery($s->callsTo('observe')[0]->argument(0), new \Deriver\Value\Projection([1])));
        self::assertSame([2], C::native($r));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testTupleKeepsTheSelectedNames(): void
    {
        $s = C::session('function target(){observe(1,2);}');
        $site = $s->callsTo('observe')[0];
        $r = $s->derive(new \Deriver\Query\TupleQuery($site->beforeInvocation(), ['left' => $site->argument(0),'right' => $site->argument(1)]));
        self::assertSame(['left','right'], array_keys($r->normalOutcomes[0]->values));
    }
}
