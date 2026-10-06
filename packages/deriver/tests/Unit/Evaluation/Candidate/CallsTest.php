<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class CallsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testValueExpandsOnlyADemandedCall(): void
    {
        $e = F::evaluator('function one(){return 1;}function target(){return one()+2;}');
        self::assertSame(3, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCapturesReadTheCreationDefinition(): void
    {
        $e = F::evaluator('function target(){$x=7;$f=function()use($x){return $x;};$x=9;return $f();}');
        self::assertSame(7, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testPassThroughRecursionClosesOnlyProvenUnchangedBindings(): void
    {
        $e = F::evaluator('function target($x){return target($x);}');
        $r = $e->returns(F::frame($e), 64);
        self::assertSame('operation', $r->kind);
        self::assertNotEmpty($r->operands);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testBindKeepsNamedArgumentsLazy(): void
    {
        $e = F::evaluator('function named($a,$b){return $a;}function target(){return named(b:missing(),a:4);}');
        self::assertSame(4, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testSelectedEffectUpdatesOnlyAReferenceArgument(): void
    {
        $e = F::evaluator('function change(&$x){$x=8;}function target(){$x=1;change($x);return $x;}');
        self::assertSame(8, F::value($e)->native());
        self::assertSame(['change' => 1], $e->context->bodies);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testFinalStorageKeepsTheLastWriteAtEachReturn(): void
    {
        $e = F::evaluator('function change(&$x){$x=2;$x=3;return 7;}function target(){$x=1;change($x);return $x;}');
        self::assertSame(3, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExpandResolvesAnAlreadySelectedTarget(): void
    {
        $e = F::evaluator('function one(){return 1;}function target(){return one();}');
        $f = F::frame($e);
        $call = F::instruction($f, 'invoke');
        self::assertSame(1, (new \Deriver\Evaluation\Candidate\Calls($e))->expand($f, $call, 'one', null, 64)->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSignatureKeepsTypeErrorsIndependentOfTheBody(): void
    {
        $e = F::evaluator('function one(int $x){return 1;}function target(){return one([]);}');
        $f = F::frame($e);
        $call = F::instruction($f, 'invoke');
        $error = (new \Deriver\Evaluation\Candidate\Calls($e))->signature($f, $call, F::frame($e, 'one')->graph, 64);
        self::assertNotNull($error);
        self::assertSame('TypeError', $error->literal);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownWritePreservesAReadBeforeTheCall(): void
    {
        $e = F::evaluator('function target(){$x=3;missing($x);return $x;}');
        $f = F::frame($e);
        $call = F::instruction($f, 'invoke');
        $a = F::instruction($f, 'local');
        $v = (new \Deriver\Evaluation\Candidate\Calls($e))->unknownWrite($f, $call, $a->result, 64);
        self::assertNotNull($v);
        self::assertSame(3, $v->operands[0]->literal);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEffectUsesTheConcreteReceiverForReferenceWrites(): void
    {
        $e = F::evaluator('class A{function change(&$x){$x=1;}}class B extends A{function change(&$x){$x=7;}}function apply(A $b){$x=0;$b->change($x);return $x;}function target(){return apply(new B);}');
        self::assertSame(7, F::value($e)->native());
        self::assertArrayNotHasKey('A::change', $e->context->bodies);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testPassedIgnoresStorageNotPassedToTheCall(): void
    {
        $e = F::evaluator('function target(){$safe=3;missing(1);return $safe;}');
        $f = F::frame($e);
        self::assertNull((new \Deriver\Evaluation\Candidate\Calls($e))->passed($f, F::instruction($f, 'invoke'), F::instruction($f, 'local')->result));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testStableEnvironmentRejectsMutableObjectInputs(): void
    {
        $e = F::evaluator('class Box{public $n=4;}function target(Box $box){$box->n--;return target($box);}');
        $f = F::frame($e);
        self::assertFalse((new \Deriver\Evaluation\Candidate\Calls($e))->stableEnvironment($f));
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testInvokePreservesCallDependencies(): void
    {
        $result = \Tests\Fake\CandidateApi::returns('function f($x){return $x+1;}function target(){return f(4);}');
        self::assertSame(5, $result->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testMissingPreservesCallDependencies(): void
    {
        $result = \Tests\Fake\CandidateApi::returns('function target(){return external(1);}');
        self::assertSame('partials', $result->candidates[0]->type);
        self::assertStringContainsString('MISSING_SOURCE', $result->toJson());
    }

}
