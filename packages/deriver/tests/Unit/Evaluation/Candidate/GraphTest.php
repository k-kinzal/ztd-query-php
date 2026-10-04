<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;

#[CoversNothing]
final class GraphTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testReturnsIndexesAllReturnOriginsWithoutEvaluatingThem(): void
    {
        $e = CandidateFixture::evaluator('function target($flag){if($flag){return 1;}return external();}');
        $f = CandidateFixture::frame($e);
        self::assertCount(2, $f->graph->returns());
        self::assertSame(0, $e->context->bodyExpansions);
    }
}
