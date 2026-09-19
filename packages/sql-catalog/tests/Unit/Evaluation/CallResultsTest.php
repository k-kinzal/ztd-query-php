<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\CallResults;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;

#[CoversClass(CallResults::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
final class CallResultsTest extends TestCase
{
    public function testKeyForTellsReadingsOfTheSameCallApart(): void
    {
        $results = new CallResults();

        self::assertSame(
            $results->keyFor('f', [Domain::literal('a')]),
            $results->keyFor('f', [Domain::literal('a')]),
        );
        self::assertNotSame(
            $results->keyFor('f', [Domain::literal('a')]),
            $results->keyFor('f', [Domain::literal('b')]),
        );
        self::assertNotSame($results->keyFor('f', []), $results->keyFor('g', []));
    }

    public function testRecallAnswersWithWhatWasRemembered(): void
    {
        $results = new CallResults();
        $result = Domain::literal('SELECT 1');
        $results->remember('f|', $result);

        self::assertSame($result, $results->recall('f|'));
        self::assertNull($results->recall('g|'));
    }

    public function testRememberStopsOnceTheMemoryIsFull(): void
    {
        $results = new CallResults();
        array_map(
            static fn (int $index): null => $results->remember('f' . $index, Domain::literal($index)),
            range(0, CallResults::MAX_REMEMBERED),
        );

        self::assertSame(CallResults::MAX_REMEMBERED, $results->count());
    }

    public function testCountIsZeroBeforeAnythingIsRemembered(): void
    {
        self::assertSame(0, (new CallResults())->count());
    }
}
