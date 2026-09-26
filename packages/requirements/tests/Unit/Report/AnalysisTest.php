<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Item;
use Requirements\Model\Source;
use Requirements\Report\Analysis;
use Requirements\Report\SourceUnit;
use Requirements\Source\Unit;

#[CoversClass(Analysis::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(Item::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[Small]
final class AnalysisTest extends TestCase
{
    /**
     * @param list<string>|null $keys
     * @param array{total: int, accounted: int, supported: int, unsupported: int, uncovered: int, percentage: float|null} $expected
     */
    #[DataProvider('providerSummaryKeys')]
    public function testSummaryCountsTheGivenUnits(?array $keys, array $expected): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $supported = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $unsupported = new Item('SPEC-002', 'specification', 'The parser shall read digits.', 'unsupported', $source, [], [], [], [], [], '', 'sourced', 'Out of scope.', 'definition.yaml', []);
        $a = new SourceUnit($source, new Unit('p:1', 'First.'));
        $a->claims = ['SPEC-001' => $supported];
        $b = new SourceUnit($source, new Unit('p:2', 'Second.'));
        $b->claims = ['SPEC-002' => $unsupported];
        $c = new SourceUnit($source, new Unit('p:3', 'Third.'));
        $d = new SourceUnit($source, new Unit('p:4', 'Fourth.'));
        $d->claims = ['SPEC-002' => $unsupported, 'SPEC-001' => $supported];
        $analysis = new Analysis(['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d], ['manual' => ['a', 'b', 'c', 'd']], [], []);
        self::assertSame($expected, $analysis->summary($keys));
    }

    /**
     * @return array<string, array{list<string>|null, array{total: int, accounted: int, supported: int, unsupported: int, uncovered: int, percentage: float|null}}>
     */
    public static function providerSummaryKeys(): array
    {
        return [
            'every unit' => [null, ['total' => 4, 'accounted' => 3, 'supported' => 2, 'unsupported' => 1, 'uncovered' => 1, 'percentage' => 75.0]],
            'supported' => [['a'], ['total' => 1, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => 100.0]],
            'unsupported' => [['b'], ['total' => 1, 'accounted' => 1, 'supported' => 0, 'unsupported' => 1, 'uncovered' => 0, 'percentage' => 100.0]],
            'uncovered' => [['c'], ['total' => 1, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 1, 'percentage' => 0.0]],
            'mixed claims count as supported once' => [['d'], ['total' => 1, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => 100.0]],
            'three of four' => [['a', 'b', 'c'], ['total' => 3, 'accounted' => 2, 'supported' => 1, 'unsupported' => 1, 'uncovered' => 1, 'percentage' => 200.0 / 3]],
            'no keys' => [[], ['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null]],
        ];
    }

    public function testSummaryHasNoPercentageWithoutUnits(): void
    {
        $analysis = new Analysis([], [], ['manual: Scope selected no source units.'], []);
        self::assertSame(['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null], $analysis->summary());
    }
}
