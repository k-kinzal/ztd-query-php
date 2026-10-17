<?php

declare(strict_types=1);

namespace Tests\Unit\Candidate\Index;

use Deriver\Candidate\Index\BodyIndex;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class BodyIndexTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testReturnsIndexesAllReturnOriginsWithoutEvaluatingThem(): void
    {
        $e = CandidateFixture::evaluator('function target($flag){if($flag){return 1;}return external();}');
        $f = CandidateFixture::frame($e);
        self::assertCount(2, $f->graph->returns());
        self::assertSame(0, $e->context->bodyExpansions);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testReturnsPreservesTheDefinitionBeforeFinally(): void
    {
        self::assertSame(2, \Tests\Fake\CandidateApi::returns('function target(){try{return 1;}finally{return 2;}}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testWritesIncludesEveryDirectStorageMutation(): void
    {
        $frame = F::frame(F::evaluator('function target(){$x=1;$x+=2;$x++;unset($x);}'));
        self::assertTrue(BodyIndex::writes(F::instruction($frame, 'write')));
        self::assertTrue(BodyIndex::writes(F::instruction($frame, 'compound')));
        self::assertTrue(BodyIndex::writes(F::instruction($frame, 'increment')));
        self::assertTrue(BodyIndex::writes(F::instruction($frame, 'unset')));
        self::assertFalse(BodyIndex::writes(F::instruction($frame, 'local')));
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testRootFindsThePropertyBehindAnElementMutation(): void
    {
        $frame = F::frame(F::evaluator('class Box{public $a=[1];function target(){$this->a[0]+=2;}}'), 'Box::target');
        self::assertSame('field-address', $frame->graph->root(F::instruction($frame, 'compound'))?->operation);
    }
}
