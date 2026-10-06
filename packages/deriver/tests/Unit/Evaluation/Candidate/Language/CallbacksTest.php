<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Language;

use Deriver\Evaluation\Candidate\Language\Callbacks as Subject;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class CallbacksTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testApplyExpandsFiniteMap(): void
    {
        $engine = F::evaluator('function target(){return array_map(fn($x)=>$x+1,[1,2]);}');
        self::assertSame([2,3], F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testMapManyPreservesLongestInput(): void
    {
        $engine = F::evaluator('function target(){return array_map(null,[1,2],[3]);}');
        self::assertSame([[1,3],[2,null]], F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSortUsesTheCapturedComparator(): void
    {
        $engine = F::evaluator('function target(){$a=[3,1,2];usort($a,fn($x,$y)=>$x<=>$y);return $a;}');
        self::assertSame([1,2,3], F::value($engine)->native());
    }

    public function testResidualKeepsItsCallbackAndOperands(): void
    {
        $array = Term::parameter('items', 'array');
        $callback = Term::constant('f');
        $result = (new Subject())->residual('array_map', [$callback,$array]);
        self::assertSame([$callback,$array], $result->operands);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCollectFiltersUsingTheCapturedPredicate(): void
    {
        self::assertSame([1 => 2,2 => 3], A::returns('function target(){return array_filter([1,2,3],fn($x)=>$x>1);}')->candidates[0]->result);
    }

}
