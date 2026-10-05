<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use Deriver\Evaluation\Candidate\Invocation\Bodies;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Project\Configuration;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;
use Tests\Fake\PlanModel;

#[CoversNothing]
final class BodiesTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSelectSharesOneReplacementAcrossOutputRequests(): void
    {
        $engine = F::evaluator('function work(){return missing();}function target(){return work();}', new Configuration(models: [new PlanModel(new ModelDescriptor('work', '1', 'work'), new SemanticPlan([]))]));
        $frame = F::frame($engine);
        $call = F::instruction($frame, 'invoke');
        $selector = new Bodies($engine);
        $first = $selector->select($frame, $call, 'work', 32);
        self::assertTrue($first->replacement);
        self::assertSame($first, $selector->select($frame, $call, 'work', 32));
        self::assertSame(1, $engine->context->modelApplications);
        self::assertSame([], $engine->context->bodies);
    }
}
