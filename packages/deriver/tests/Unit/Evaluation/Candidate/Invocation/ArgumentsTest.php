<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class ArgumentsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testActualsExpandsNamedUnpack(): void
    {
        $engine = F::evaluator('function callee($a,$b){return [$a,$b];}function target(){return callee(...["b"=>2,"a"=>1]);}');
        self::assertSame([1,2], F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testBindPreservesVariadicNames(): void
    {
        $engine = F::evaluator('function callee($a,...$more){return $more;}function target(){return callee(1,x:2,y:3);}');
        self::assertSame(['x' => 2,'y' => 3], F::value($engine)->native());
    }

}
