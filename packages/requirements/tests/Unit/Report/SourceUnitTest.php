<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Item;
use Requirements\Model\Source;
use Requirements\Report\SourceUnit;
use Requirements\Source\Unit;

#[CoversClass(SourceUnit::class)]
#[UsesClass(Item::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[Small]
final class SourceUnitTest extends TestCase
{
    public function testSupportedIsFalseWithoutClaims(): void
    {
        $unit = new SourceUnit(new Source('manual', 'source.html', 'html', 'main p'), new Unit('p:1', 'Names start with a letter.'));
        self::assertFalse($unit->supported());
    }

    public function testSupportedIsFalseWithOnlyUnsupportedClaims(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $unit = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $unit->claims = [
            'SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall read names.', 'unsupported', $source, [], [], [], [], [], '', 'sourced', 'Out of scope.', 'definition.yaml', []),
            'SPEC-002' => new Item('SPEC-002', 'specification', 'The parser shall read digits.', 'unsupported', $source, [], [], [], [], [], '', 'sourced', 'Out of scope.', 'definition.yaml', []),
        ];
        self::assertFalse($unit->supported());
    }

    public function testSupportedIsTrueWhenAnyClaimIsSupported(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $unit = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $unit->claims = [
            'SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall read names.', 'unsupported', $source, [], [], [], [], [], '', 'sourced', 'Out of scope.', 'definition.yaml', []),
            'SPEC-002' => new Item('SPEC-002', 'specification', 'The parser shall read digits.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []),
        ];
        self::assertTrue($unit->supported());
    }

    /**
     * @throws JsonException
     */
    public function testFingerprintHashesTheTextWithoutClaims(): void
    {
        $unit = new SourceUnit(new Source('manual', 'source.html', 'html', 'main p'), new Unit('p:1', 'Names start with a letter.'));
        self::assertSame(hash('sha256', '["Names start with a letter.",[]]'), $unit->fingerprint([]));
    }

    /**
     * @throws JsonException
     */
    public function testFingerprintHashesClaimsAndRefinedRequirementsByID(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $requirement = new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'REQ-001']);
        $specification = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], ['REQ-001'], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-001', 'requirements' => ['REQ-001']]);
        $unit = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $unit->claims = ['SPEC-001' => $specification];
        self::assertSame(hash('sha256', '["Names start with a letter.",{"REQ-001":{"id":"REQ-001"},"SPEC-001":{"id":"SPEC-001","requirements":["REQ-001"]}}]'), $unit->fingerprint(['REQ-001' => $requirement, 'SPEC-001' => $specification]));
    }

    /**
     * @throws JsonException
     */
    public function testFingerprintIgnoresTheOrderOfClaims(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $first = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-001']);
        $second = new Item('SPEC-002', 'specification', 'The parser shall read digits.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-002']);
        $forward = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $forward->claims = ['SPEC-001' => $first, 'SPEC-002' => $second];
        $backward = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $backward->claims = ['SPEC-002' => $second, 'SPEC-001' => $first];
        self::assertSame($forward->fingerprint([]), $backward->fingerprint([]));
    }

    /**
     * @throws JsonException
     */
    public function testFingerprintChangesWithTheRefinedRequirementRecord(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $specification = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], ['REQ-001'], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-001']);
        $before = new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'REQ-001']);
        $after = new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'REQ-001', 'labels' => ['names']]);
        $unit = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $unit->claims = ['SPEC-001' => $specification];
        self::assertNotSame($unit->fingerprint(['REQ-001' => $before]), $unit->fingerprint(['REQ-001' => $after]));
    }

    /**
     * @throws JsonException
     */
    public function testToArrayReportsAnUncoveredUnit(): void
    {
        $unit = new SourceUnit(new Source('manual', 'source.html', 'html', 'main p'), new Unit('p:1', 'Names start with a letter.'));
        self::assertSame(['uri' => 'source.html', 'format' => 'html', 'location' => 'p:1', 'text' => 'Names start with a letter.', 'claims' => [], 'status' => 'uncovered', 'fingerprint' => hash('sha256', '["Names start with a letter.",[]]')], $unit->toArray([]));
    }

    /**
     * @throws JsonException
     */
    public function testToArrayReportsAnUnsupportedUnit(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'unsupported', $source, [], [], [], [], [], '', 'sourced', 'Out of scope.', 'definition.yaml', ['id' => 'SPEC-001']);
        $unit = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $unit->claims = ['SPEC-001' => $item];
        self::assertSame(['uri' => 'source.html', 'format' => 'html', 'location' => 'p:1', 'text' => 'Names start with a letter.', 'claims' => ['SPEC-001'], 'status' => 'unsupported', 'fingerprint' => hash('sha256', '["Names start with a letter.",{"SPEC-001":{"id":"SPEC-001"}}]')], $unit->toArray(['SPEC-001' => $item]));
    }

    /**
     * @throws JsonException
     */
    public function testToArrayReportsASupportedUnit(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $first = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'unsupported', $source, [], [], [], [], [], '', 'sourced', 'Out of scope.', 'definition.yaml', ['id' => 'SPEC-001']);
        $second = new Item('SPEC-002', 'specification', 'The parser shall read digits.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-002']);
        $unit = new SourceUnit($source, new Unit('p:1', 'Names start with a letter.'));
        $unit->claims = ['SPEC-001' => $first, 'SPEC-002' => $second];
        $record = $unit->toArray([]);
        self::assertSame(['SPEC-001', 'SPEC-002'], $record['claims']);
        self::assertSame('supported', $record['status']);
    }
}
