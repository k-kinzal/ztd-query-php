<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\ItemRecord;
use Requirements\Model\Item;
use Requirements\Model\Source;

#[CoversClass(ItemRecord::class)]
#[UsesClass(Item::class)]
#[UsesClass(Source::class)]
#[Small]
final class ItemRecordTest extends TestCase
{
    public function testDescribeReturnsTheCompleteTraceabilityRecord(): void
    {
        $item = new Item(
            'SPEC-001',
            'specification',
            'The reader shall preserve positions.',
            'unsupported',
            new Source('manual', 'source.html', 'html', 'main p'),
            [],
            [],
            ['REQ-001'],
            ['SPEC-002'],
            ['diagnostics', 'reader'],
            'reader',
            'sourced',
            'The editor owns this behavior.',
            'definition.yaml',
            ['design' => [['text' => 'Preserve offsets.']], 'metadata' => ['owner' => 'parser'], 'tests' => [['runner' => 'unit', 'target' => 'ReaderTest::testA']]],
        );
        self::assertSame([
            'id' => 'SPEC-001',
            'kind' => 'specification',
            'statement' => 'The reader shall preserve positions.',
            'support' => 'unsupported',
            'source' => 'manual',
            'origin' => 'sourced',
            'reason' => 'The editor owns this behavior.',
            'labels' => ['diagnostics', 'reader'],
            'category' => 'reader',
            'requirements' => ['REQ-001'],
            'related' => ['SPEC-002'],
            'design' => [['text' => 'Preserve offsets.']],
            'metadata' => ['owner' => 'parser'],
            'test_references' => [['runner' => 'unit', 'target' => 'ReaderTest::testA']],
        ], ItemRecord::describe($item));
    }

    public function testDescribeDefaultsMissingSourceDesignMetadataAndTests(): void
    {
        $item = new Item('ORIGINAL-001', 'requirement', 'Names may contain digits.', 'supported', null, [], [], [], [], [], '', 'original', 'Avoid data loss.', 'definition.yaml', ['id' => 'ORIGINAL-001']);
        self::assertSame([
            'id' => 'ORIGINAL-001',
            'kind' => 'requirement',
            'statement' => 'Names may contain digits.',
            'support' => 'supported',
            'source' => null,
            'origin' => 'original',
            'reason' => 'Avoid data loss.',
            'labels' => [],
            'category' => '',
            'requirements' => [],
            'related' => [],
            'design' => [],
            'metadata' => [],
            'test_references' => [],
        ], ItemRecord::describe($item));
    }
}
