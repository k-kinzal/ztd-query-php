<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use Deriver\Evaluation\Candidate\Invocation\Body;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class BodyTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testEnterCountsOnlySelectedSourceImplementations(): void
    {
        $engine = F::evaluator();
        $graph = F::frame($engine)->graph;
        (new Body($graph, true))->enter($engine->context);
        self::assertSame([], $engine->context->bodies);
        (new Body($graph, false))->enter($engine->context);
        self::assertSame(['target' => 1], $engine->context->bodies);
    }
}
