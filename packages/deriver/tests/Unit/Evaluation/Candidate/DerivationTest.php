<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;

#[CoversNothing]
final class DerivationTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testValueSharesImmutableDependencyNodes(): void
    {
        $e = CandidateFixture::evaluator();
        $f = CandidateFixture::frame($e);
        $i = CandidateFixture::instruction($f, 'binary');
        $a = $e->value($f, $i->result, 20);
        self::assertSame($a, $e->value($f, $i->result, 20));
        self::assertGreaterThan(0, $e->context->sharedNodeHits);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testInstructionKeepsTheOperatorWithAnUnknownOperand(): void
    {
        $e = CandidateFixture::evaluator();
        $f = CandidateFixture::frame($e);
        $v = $e->instruction($f, CandidateFixture::instruction($f, 'binary'), 20);
        self::assertSame('+', $v->literal);
        self::assertSame('reference', $v->operands[0]->kind);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testReturnsIgnoresUnrelatedCalls(): void
    {
        $e = CandidateFixture::evaluator('function target(){unrelated();return 12;}');
        $f = CandidateFixture::frame($e);
        self::assertSame(12, $e->returns($f, 20)->native());
        self::assertSame(0, $e->context->bodyExpansions);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testPhiPrunesAKnownCondition(): void
    {
        $e = CandidateFixture::evaluator('function target(){return true ? 3 : external();}');
        $f = CandidateFixture::frame($e);
        $v = $e->phi($f, CandidateFixture::instruction($f, 'phi'), 20);
        self::assertSame(3, iterator_to_array((new Choices())->alternatives($v), false)[0][0]->native());
        self::assertSame(0, $e->context->bodyExpansions);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testOperationUsesTheOrdinaryArithmeticRules(): void
    {
        $e = CandidateFixture::evaluator();
        self::assertSame(12, $e->operation('binary', '+', [Term::constant(5),Term::constant(7)])->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testEvaluateRetainsUnsupportedOperands(): void
    {
        $e = CandidateFixture::evaluator();
        $known = Term::constant(5);
        $v = $e->evaluate('not-supported', '', [$known]);
        self::assertSame([$known], $v->operands);
        self::assertSame('UNSUPPORTED_OPERATION', $v->attributes['reason']);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testElementRespectsNumericStringKeys(): void
    {
        $e = CandidateFixture::evaluator();
        self::assertSame(3, $e->element(Term::array([-1 => Term::constant(3)]), Term::constant('-1'))->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testIntrinsicSimplifiesKnownLibraryInputs(): void
    {
        $e = CandidateFixture::evaluator();
        self::assertSame(3, $e->intrinsic('strlen', [Term::constant('abc')])->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testConstantKeepsMissingConstantIdentity(): void
    {
        $e = CandidateFixture::evaluator('function target(){return UNKNOWN_NAME;}');
        $f = CandidateFixture::frame($e);
        $v = $e->constant($f, CandidateFixture::instruction($f, 'constant-fetch'), 20);
        self::assertSame('UNKNOWN_NAME', $v->literal);
        self::assertSame('MISSING_CONSTANT', $v->attributes['reason']);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testExpressionUsesTheSameCallbackAndCastRules(): void
    {
        self::assertSame('v3', \Tests\Fake\CandidateApi::returns('function target(){return "v".call_user_func(fn($x)=>$x+1,2);}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testBoundedKeepsTheSameEvidenceWhenReused(): void
    {
        $session = \Tests\Fake\CandidateApi::session('function target(){return external(1);}');
        $query = new \Deriver\Query\ReturnQuery('target');
        $first = $session->derive($query);
        $session->release();
        $second = $session->derive($query);
        self::assertSame($first->toJson(), $second->toJson());
    }

}
