<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;

#[CoversNothing]
final class ContextTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testBoundaryKeepsDepthAndResourceReasonsSeparate(): void
    {
        $e = CandidateFixture::evaluator();
        self::assertSame('DEPTH_LIMIT', $e->context->boundary(0));
        self::assertNull($e->context->boundary(1));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testReferenceCarriesSourceScopeTypeAndStopReason(): void
    {
        $e = CandidateFixture::evaluator();
        $f = CandidateFixture::frame($e);
        $r = $e->context->reference($f, 'x', $f->graph->body->source, 'int', 'DEPTH_LIMIT', 'deferred');
        self::assertSame('target', $r->attributes['scope']);
        self::assertSame('int', $r->attributes['type']);
        self::assertSame('DEPTH_LIMIT', $r->attributes['reason']);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testRecordCapturesOnlyRequestedInstructionEvidence(): void
    {
        $e = CandidateFixture::evaluator();
        $f = CandidateFixture::frame($e);
        $i = CandidateFixture::instruction($f, 'binary');
        $e->context->record($f, $i);
        self::assertCount(1, $e->context->evidence);
        self::assertSame('binary', array_values($e->context->evidence)[0]->operation);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testWorkStopsAtTheTransferBudget(): void
    {
        $engine = CandidateFixture::evaluator(budget:new \Deriver\Query\Budget(transfers:1));
        self::assertNull($engine->context->work());
        self::assertSame('BUDGET_EXCEEDED', $engine->context->work());
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testAcceptEvidenceCountsSharedNodesOnce(): void
    {
        $engine = CandidateFixture::evaluator();
        $node = new \Deriver\Result\Evidence\Node('operation', attributes:['operation' => 'literal']);
        $engine->context->acceptEvidence(new \Deriver\Value\Term('constant', 1, evidence:$node));
        $engine->context->acceptEvidence(new \Deriver\Value\Term('constant', 1, evidence:$node));
        self::assertCount(1, $engine->context->proofNodes);
    }

}
