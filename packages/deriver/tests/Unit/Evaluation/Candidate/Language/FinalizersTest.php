<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Language;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FinalizersTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testApplyUsesConditionalFinallyCompletion(): void
    {
        $source = 'function helper($x){try{return 1;}finally{if($x){return 2;}}}function target(){return [helper(true),helper(false)];}';
        self::assertSame([2,1], \Tests\Fake\CandidateApi::returns($source)->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCompletionsStayInsideTheFinallyRegion(): void
    {
        $frame = \Tests\Fake\CandidateFixture::frame(\Tests\Fake\CandidateFixture::evaluator('function target(){try{return 1;}finally{return 2;}}'));
        $region = $frame->graph->body->regions[0];
        self::assertSame([$region->finally], (new \Deriver\Evaluation\Candidate\Language\Finalizers())->completions($frame, $region));
    }

}
