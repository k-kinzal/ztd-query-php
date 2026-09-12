<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Coverage\CoverageSets;

#[CoversClass(CoverageSets::class)]
final class CoverageSetsTest extends TestCase
{
    public function testIncludeUnionsProductionIdsWithoutDoubleCounting(): void
    {
        $one = new CoverageSets();
        $one->reached = ['a' => true, 'outside' => true];
        $one->emitted = ['a' => true];
        $two = new CoverageSets();
        $two->reached = ['b' => true];
        $two->emitted = ['b' => true];
        $two->include($one);
        $two->include($one);
        self::assertSame(2, $two->measurement(['a', 'b'])['reached']);
        self::assertSame(2, $two->measurement(['a', 'b'])['emitted']);
        self::assertSame([], $two->measurement(['a', 'b'])['notReachedIds']);
    }

    public function testMeasurementHandlesAnEmptyDenominator(): void
    {
        $measurement = (new CoverageSets())->measurement([]);
        self::assertSame(0.0, $measurement['reachedRate']);
        self::assertSame(0.0, $measurement['emittedRate']);
    }

    public function testMeasurementExposesStableSortedSetsAndBothRatesForTheFullInventory(): void
    {
        $sets = new CoverageSets();
        $sets->reached = ['c' => true, 'a' => true, 'outside' => true];
        $sets->emitted = ['outside' => true, 'a' => true];
        self::assertSame([
            'reachedIds' => ['a', 'c', 'outside'], 'emittedIds' => ['a', 'outside'],
            'notReachedIds' => ['b', 'd'], 'notEmittedIds' => ['c'],
            'reached' => 2, 'emitted' => 1, 'total' => 4, 'reachedRate' => 0.5, 'emittedRate' => 0.25,
        ], $sets->measurement(['a', 'b', 'c', 'd']));
    }

}
