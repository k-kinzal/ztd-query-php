<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class ArrayConstructionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testValueDoesNotLoseAnUnknownMiddleEntry(): void
    {
        $e = CandidateFixture::evaluator('function target($x){return ["head",$x,"tail"];}');
        $f = CandidateFixture::frame($e);
        $v = $e->returns($f, 20);
        self::assertSame('head', $v->operands[0]->literal);
        self::assertSame('reference', $v->operands[1]->kind);
        self::assertSame('tail', $v->operands[2]->literal);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAppendPreservesTheOrderAfterAnUnknownKey(): void
    {
        $e = F::evaluator();
        $entries = [];
        $next = 0;
        $integer = false;
        $residual = null;
        $builder = new \Deriver\Evaluation\Candidate\ArrayConstruction($e);
        $builder->append($entries, $next, $integer, $residual, Term::parameter('key'), Term::constant('value'));
        self::assertNotNull($residual);
        self::assertSame('array-set', $residual->kind);
        self::assertSame('key', $residual->operands[1]->literal);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testChainPreservesAppendAfterAnExplicitKey(): void
    {
        self::assertSame([4 => 'a',5 => 'b'], \Tests\Fake\CandidateApi::returns('function target(){return [4=>"a","b"];}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testChoicesPreservesCorrelationBetweenArrayEntries(): void
    {
        $result = \Tests\Fake\CandidateApi::returns('function target($b){$x=$b?1:2;return [$x,$x];}');
        self::assertSame([[1,1],[2,2]], array_column($result->candidates, 'result'));
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testChoicesLimitRetainsFixedArrayEntries(): void
    {
        $result = \Tests\Fake\CandidateApi::returns('function target($b){$x=$b?1:2;return ["head",$x,"tail"];}', budget:new \Deriver\Query\Budget(partitions:1));
        $candidate = $result->candidates[0];
        self::assertSame('partials', $candidate->type);
        self::assertSame('head', $candidate->term->operands[0]->literal);
        self::assertSame('tail', $candidate->term->operands[2]->literal);
        self::assertSame('ENUMERATION_LIMIT', $candidate->term->attributes['reason']);
    }

}
