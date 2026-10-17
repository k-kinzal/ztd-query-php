<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class QueryExecutionTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testDeriveUsesTheCandidateContractByDefault(): void
    {
        $r = C::argument(C::session('function target(){observe(1);}'));
        self::assertSame(1, $r->count());
        self::assertSame(\Deriver\Result\CandidateStatus::Analyzed, $r[0]->type);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testDeriveBuildsTheCandidatesFromOneContextPerQuery(): void
    {
        $r = C::session('function target(){return 1;}')->derive(new \Deriver\Query\ReturnQuery('target'));
        self::assertSame([1], C::native($r));
        self::assertFalse($r->interrupted);
        self::assertSame(\Deriver\Result\CandidateStatus::Analyzed, $r[0]->type);
    }
}
