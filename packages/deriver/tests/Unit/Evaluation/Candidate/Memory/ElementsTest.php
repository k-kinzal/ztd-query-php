<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class ElementsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testWritePreservesUnchangedSiblings(): void
    {
        $engine = F::evaluator('function target(){$a=["x"=>["y"=>1,"z"=>2]];$a["x"]["y"]=3;return $a;}');
        self::assertSame(['x' => ['y' => 3,'z' => 2]], F::value($engine)->native());
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testMutatePreservesNestedCompoundAndUnset(): void
    {
        self::assertSame(['x' => ['y' => 3]], A::returns('function target(){$a=["x"=>["y"=>1,"z"=>9]];$a["x"]["y"]+=2;unset($a["x"]["z"]);return $a;}')->candidates[0]->result);
    }

}
