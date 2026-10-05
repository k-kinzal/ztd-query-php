<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use Deriver\Evaluation\Candidate\Invocation\Origin;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class OriginTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testCallRetainsCallerDerivedInputsWithoutEnteringTheBody(): void
    {
        $engine = F::evaluator('function change($value){missing();}function target(){change(7);}');
        [$frame, $call] = (new Origin())->call(F::frame($engine, 'change')->graph);
        $value = $engine->value($frame, $call->arguments[0]->register, 64);
        self::assertSame(7, iterator_to_array((new \Deriver\Evaluation\Candidate\Choices())->alternatives($value), false)[0][0]->native());
        self::assertSame([], $engine->context->bodies);
    }
}
