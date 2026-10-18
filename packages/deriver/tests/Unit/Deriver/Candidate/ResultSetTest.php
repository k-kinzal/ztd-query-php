<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Candidate;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\ResultSet;
use Deriver\Deriver\Candidate\Statistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResultSet::class)]
#[UsesClass(CandidateCollection::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(Statistics::class)]
#[Small]
final class ResultSetTest extends TestCase
{
    public function testPreservesItsSemanticContract(): void
    {
        $first = new CandidateCollection([], new ResultRef('first'));
        $second = new CandidateCollection([], new ResultRef('second'));
        $batch = new ResultSet([$second, $first]);
        self::assertSame([$second, $first], $batch->results);
        self::assertSame('second', $batch->results[0]->reference->id);
        self::assertSame('first', $batch->results[1]->reference->id);
    }
}
