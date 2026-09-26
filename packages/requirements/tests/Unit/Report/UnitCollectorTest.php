<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Report\SourceUnit;
use Requirements\Report\UnitCollector;
use Requirements\Source\Unit;
use RuntimeException;

#[CoversClass(UnitCollector::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[Small]
final class UnitCollectorTest extends TestCase
{
    public function testOpenStartsAScopeWithNoUnits(): void
    {
        $collector = new UnitCollector();
        $collector->open(new Source('manual', 'source.html', 'html', 'main p'));
        self::assertSame(['manual' => []], $collector->scopes);
        self::assertSame([], $collector->units);
    }

    public function testOpenClearsTheUnitKeysOfAReopenedScope(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $collector = new UnitCollector();
        $collector->open($source);
        $collector->collect($source, [new Unit('p:1', 'First.')]);
        $collector->open($source);
        self::assertSame(['manual' => []], $collector->scopes);
        self::assertCount(1, $collector->units);
    }

    public function testCollectAddsTheSelectedUnitsByKey(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $first = new Unit('p:1', 'First.');
        $second = new Unit('p:2', 'Second.');
        $collector = new UnitCollector();
        $collector->open($source);
        $collector->collect($source, [$first, $second]);
        self::assertSame(['manual' => [$first->key($source), $second->key($source)]], $collector->scopes);
        self::assertSame([$first->key($source), $second->key($source)], array_keys($collector->units));
        self::assertSame($source, $collector->units[$first->key($source)]->source);
        self::assertSame($second, $collector->units[$second->key($source)]->unit);
        self::assertSame([], $collector->units[$first->key($source)]->claims);
    }

    public function testCollectListsARepeatedUnitOnce(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $first = new Unit('p:1', 'First.');
        $second = new Unit('p:2', 'Second.');
        $collector = new UnitCollector();
        $collector->open($source);
        $collector->collect($source, [$first, new Unit('p:1', 'First.'), $second]);
        self::assertSame(['manual' => [$first->key($source), $second->key($source)]], $collector->scopes);
        self::assertCount(2, $collector->units);
    }

    public function testCollectSharesAUnitBetweenScopesOfTheSameDocument(): void
    {
        $manual = new Source('manual', 'source.html', 'html', 'main p');
        $overlap = new Source('overlap', 'source.html', 'html', '#a');
        $unit = new Unit('p:1', 'First.');
        $collector = new UnitCollector();
        $collector->open($manual);
        $collector->collect($manual, [$unit, new Unit('p:2', 'Second.')]);
        $collector->open($overlap);
        $collector->collect($overlap, [new Unit('p:1', 'First.')]);
        self::assertCount(2, $collector->units);
        self::assertSame([$unit->key($manual)], $collector->scopes['overlap']);
        self::assertSame($manual, $collector->units[$unit->key($manual)]->source);
        self::assertSame($unit, $collector->units[$unit->key($manual)]->unit);
    }

    public function testCollectRejectsAnEmptyScope(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $collector = new UnitCollector();
        $collector->open($source);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scope selected no source units.');
        $collector->collect($source, []);
    }

    #[DataProvider('providerBlankUnits')]
    public function testCollectRejectsABlankUnit(string $location, string $text): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $collector = new UnitCollector();
        $collector->open($source);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source units must have nonempty locations and text.');
        $collector->collect($source, [new Unit($location, $text)]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerBlankUnits(): array
    {
        return [
            'no location' => ['', 'First.'],
            'no text' => ['p:1', ''],
        ];
    }

    public function testCollectRejectsConflictingTextOfTheSameUnit(): void
    {
        $manual = new Source('manual', 'source.html', 'html', 'main p');
        $overlap = new Source('overlap', 'source.html', 'html', '#a');
        $collector = new UnitCollector();
        $collector->open($manual);
        $collector->collect($manual, [new Unit('p:1', 'First.')]);
        $collector->open($overlap);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicting snapshots for the same source unit.');
        $collector->collect($overlap, [new Unit('p:1', 'Changed.')]);
    }

    #[DataProvider('providerOverlappingLocations')]
    public function testCollectRejectsAncestorsAndDescendantsAcrossScopes(string $existing, string $added): void
    {
        $manual = new Source('manual', 'source.html', 'html', 'main p');
        $overlap = new Source('overlap', 'source.html', 'html', 'main');
        $collector = new UnitCollector();
        $collector->open($manual);
        $collector->collect($manual, [new Unit($existing, 'First.')]);
        $collector->open($overlap);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Overlapping ancestor and descendant units across source scopes.');
        $collector->collect($overlap, [new Unit($added, 'First.')]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerOverlappingLocations(): array
    {
        return [
            'ancestor added' => ['/html/body/main/p[1]', '/html/body/main'],
            'descendant added' => ['/html/body/main', '/html/body/main/p[1]'],
        ];
    }

    #[DataProvider('providerSeparateUnits')]
    public function testCollectAcceptsUnitsThatDoNotOverlap(string $uri, string $format, string $existing, string $added): void
    {
        $manual = new Source('manual', 'source.html', 'html', 'main p');
        $other = new Source('other', $uri, $format, 'main');
        $collector = new UnitCollector();
        $collector->open($manual);
        $collector->collect($manual, [new Unit($existing, 'First.')]);
        $collector->open($other);
        $collector->collect($other, [new Unit($added, 'Second.')]);
        self::assertCount(2, $collector->units);
        self::assertCount(1, $collector->scopes['other']);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function providerSeparateUnits(): array
    {
        return [
            'other document' => ['other.html', 'html', '/html/body/main/p[1]', '/html/body/main'],
            'other format' => ['source.html', 'xml', '/html/body/main', '/html/body/main/p[1]'],
            'shared prefix without separator' => ['source.html', 'html', 'line:1', 'line:10'],
            'longer sibling' => ['source.html', 'html', 'line:10', 'line:1'],
        ];
    }

    public function testCollectKeepsTheUnitsAddedBeforeARejectedUnit(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $first = new Unit('p:1', 'First.');
        $collector = new UnitCollector();
        $collector->open($source);
        try {
            $collector->collect($source, [$first, new Unit('p:2', '')]);
            self::fail('Expected a blank unit to be rejected.');
        } catch (RuntimeException $error) {
            self::assertSame('Source units must have nonempty locations and text.', $error->getMessage());
        }
        self::assertSame([$first->key($source)], array_keys($collector->units));
        self::assertSame(['manual' => [$first->key($source)]], $collector->scopes);
    }
}
