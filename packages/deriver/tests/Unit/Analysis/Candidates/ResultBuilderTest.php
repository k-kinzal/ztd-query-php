<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class ResultBuilderTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testBuildKeepsOneSymbolicCandidateSeparateFromEnumeration(): void
    {
        $r = C::argument(C::session('function target($x){observe(5+$x);}'));
        self::assertCount(1, $r->normalOutcomes);
        self::assertSame('closed', $r->assessment->closure);
        self::assertNotNull($r->candidateGraph);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testFrontiersCarryTheUnresolvedInputIdentity(): void
    {
        $r = C::argument(C::session('function target($x){observe(5+$x);}'));
        self::assertSame('EXTERNAL_INPUT', $r->frontiers[0]->code);
        self::assertSame('candidate.php', $r->frontiers[0]->at->path);
    }
}
