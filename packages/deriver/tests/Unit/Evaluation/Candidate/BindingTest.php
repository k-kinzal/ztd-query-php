<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Evaluation\Candidate\Binding;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;

#[CoversNothing]
final class BindingTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testBindingRetainsTheCallerAndUnevaluatedRegister(): void
    {
        $e = CandidateFixture::evaluator();
        $f = CandidateFixture::frame($e);
        $binding = new Binding($f, 'r0');
        self::assertSame($f, $binding->frame);
        self::assertSame(0, $e->context->referenceExpansions);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueAppliesTheCallingScalarModeToADemandedArgument(): void
    {
        $e = CandidateFixture::evaluator('function target(){return "7";}');
        $frame = CandidateFixture::frame($e);
        $instruction = CandidateFixture::instruction($frame, 'constant');
        $binding = new Binding($frame, $instruction->result);
        self::assertSame(7, $binding->value($e, 'int', 64)->native());
    }
}
