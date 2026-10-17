<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class CandidateCollectorTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCollectKeepsOneSymbolicCandidateSeparateFromEnumeration(): void
    {
        $r = C::argument(C::session('function target($x){observe(5+$x);}'));
        self::assertCount(1, $r);
        self::assertSame(\Deriver\Result\CandidateStatus::Partials, $r[0]->type);
        self::assertSame(5, $r[0]->term->operands[0]->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testFrontiersCarryTheUnresolvedInputIdentity(): void
    {
        $r = C::argument(C::session('function target($x){observe(5+$x);}'));
        self::assertSame('EXTERNAL_INPUT', C::frontiers($r)[0]->code);
        self::assertSame('candidate.php', C::frontiers($r)[0]->at->path);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCollectRetainsTheRequestedSourceRangeAtTheEvidenceRoot(): void
    {
        $proof = \Tests\Fake\CandidateApi::returns()[0]->evidence[0];
        self::assertNotNull($proof->root->source);
        self::assertSame('candidate.php', $proof->root->source->path);
        self::assertSame('target', $proof->root->attributes['owner']);
    }

}
