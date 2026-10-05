<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class ModelsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testGraphReplacesSourceWithoutDemandingUnusedInputs(): void
    {
        $e = F::evaluator('function heavy($input,$unused){return missing();}function target(){return heavy(missing(),missing());}', new \Deriver\Project\Configuration(models:[C::model(\Deriver\Model\Plan\Expression::literal(Term::constant(7)))]));
        self::assertSame(7, F::value($e)->native());
        self::assertSame(0, $e->context->bodyExpansions);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testActualBindsNamedArguments(): void
    {
        $e = F::evaluator('function helper($a,$b){return $b;}function target(){return helper(b:7,a:9);}');
        self::assertSame(7, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testDemandUsesActualBindingsBeforeSelectingAPlan(): void
    {
        self::assertSame(3, \Tests\Fake\CandidateApi::returns('function target(){return count([1,2,3]);}')->candidates[0]->result);
    }

}
