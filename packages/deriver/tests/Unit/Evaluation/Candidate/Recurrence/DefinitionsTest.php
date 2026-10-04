<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Recurrence;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class DefinitionsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testValueSolvesOnlyTheDemandedFiniteRecurrence(): void
    {
        $e = F::evaluator('function target(){$s=0;for($i=0;$i<4;$i++){$unused=missing();$s+=$i;}return $s;}');
        self::assertSame(6, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testVersionKeepsAnEarlierDefinitionSeparate(): void
    {
        $e = F::evaluator('function target(){$s=2;for($i=0;$i<3;$i++){$s*=2;}return $s;}');
        self::assertSame(16, F::value($e)->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testPostConditionKeepsTheMandatoryFirstBody(): void
    {
        $e = F::evaluator('function target(){$x=1;do{$x+=2;}while(false);return $x;}');
        self::assertSame(3, F::value($e)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testParentsSeparatesTheFirstBodyFromLaterVersions(): void
    {
        $e = F::evaluator('function target(){$x=0;do{$x++;}while($x<3);return $x;}');
        self::assertSame(3, F::value($e)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testPredecessorDoesNotChangeAnOrdinaryFrame(): void
    {
        $e = F::evaluator();
        $frame = F::frame($e);
        self::assertSame($frame, (new \Deriver\Evaluation\Candidate\Recurrence\Definitions($e))->predecessor($frame, 0, 0));
    }
}
