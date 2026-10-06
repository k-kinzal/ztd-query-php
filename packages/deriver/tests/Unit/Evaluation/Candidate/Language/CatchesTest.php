<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Language;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class CatchesTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testResolveFollowsTheCatchSelectedByADemandedValue(): void
    {
        $e = F::evaluator('function number(int $x){return $x;}function target(){try{return number([]);}catch(TypeError $e){return "typed";}}');
        self::assertSame('typed', F::value($e)->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testRegionsIndexesTheLexicalHandler(): void
    {
        $e = F::evaluator('function target(){try{return missing();}catch(Error $e){return 1;}}');
        self::assertSame([0], (new \Deriver\Evaluation\Candidate\Language\Catches())->regions(F::frame($e), 0));
    }
}
