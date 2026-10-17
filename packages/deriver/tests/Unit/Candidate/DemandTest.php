<?php

declare(strict_types=1);

namespace Tests\Unit\Candidate;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class DemandTest extends TestCase
{
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
        self::assertNotEmpty(C::frontiers(C::argument($s)));
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
        self::assertSame(['left','right'], array_keys($r->candidates[0]->term->operands));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testReturnsRetainsConditionalImplementations(): void
    {
        $result = \Tests\Fake\CandidateApi::returns('if($flag){function target(){return 1;}}else{function target(){return 2;}}');
        self::assertSame([1,2], array_column($result->candidates, 'result'));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testProjectAppliesThePathOfTheTargetToTheSelectedValue(): void
    {
        $s = C::session('function target(){observe([\'a\' => [1, 2]]);}');
        $r = $s->derive(new \Deriver\Query\ValueQuery($s->callsTo('observe')[0]->argument(0), new \Deriver\Value\Projection(['a', 1])));
        self::assertSame([2], C::native($r));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testSelectKeepsAMissingSymbolAsAnUnexpandedReference(): void
    {
        $r = C::session('function target(){return 1;}')->derive(new \Deriver\Query\ReturnQuery('target'));
        self::assertSame([1], C::native($r));
        $missing = C::session('function target(){return missing();}')->derive(new \Deriver\Query\ReturnQuery('target'));
        self::assertSame('partials', $missing->candidates[0]->type);
    }
}
