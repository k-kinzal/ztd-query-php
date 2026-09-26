<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\LiteralMask;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Validator;
use Requirements\Ears\Wording;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Excerpt;
use Requirements\Model\Item;
use Requirements\Model\ItemValidator;
use Requirements\Model\Source;
use Requirements\Model\TestReference;

#[CoversClass(Item::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(TestReference::class)]
#[UsesClass(Source::class)]
#[UsesClass(ItemValidator::class)]
#[UsesClass(Validator::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Wording::class)]
#[Small]
final class ItemTest extends TestCase
{
    public function testFromReadsASpecificationWithDefaults(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $data = ['id' => 'SPEC-001', 'statement' => 'When a name is read, the parser shall require a leading letter.', 'evidence' => [['selector' => '#a', 'quote' => 'Names shall start with a letter.']]];
        $item = Item::from($data, $source, 'definition.yaml');
        self::assertSame('SPEC-001', $item->id);
        self::assertSame('specification', $item->kind);
        self::assertSame('When a name is read, the parser shall require a leading letter.', $item->statement);
        self::assertSame('supported', $item->status);
        self::assertSame($source, $item->source);
        self::assertEquals([new Excerpt('#a', 'Names shall start with a letter.')], $item->evidence);
        self::assertSame([], $item->tests);
        self::assertSame([], $item->requirements);
        self::assertSame([], $item->related);
        self::assertSame([], $item->labels);
        self::assertSame('', $item->category);
        self::assertSame('sourced', $item->origin);
        self::assertSame('', $item->reason);
        self::assertSame('definition.yaml', $item->file);
        self::assertSame($data, $item->data);
    }

    public function testFromReadsEveryField(): void
    {
        $data = [
            'id' => 'SPEC-002',
            'kind' => 'specification',
            'statement' => 'The reader shall retain positions.',
            'status' => 'unsupported',
            'tests' => [['runner' => 'unit', 'target' => 'Sample\PassingTest::testPass'], ['runner' => 'unit', 'target' => 'Sample\PassingTest::testData']],
            'related' => ['SPEC-001'],
            'labels' => ['parser', 'positions'],
            'category' => 'Reading',
            'origin' => 'original',
            'reason' => 'Support editors.',
            'design' => [['url' => 'https://example.org/design', 'text' => 'Design notes']],
            'metadata' => ['owner' => 'parser team'],
        ];
        $item = Item::from($data, null, 'specs/reader.yaml');
        self::assertSame('unsupported', $item->status);
        self::assertNull($item->source);
        self::assertSame([], $item->evidence);
        self::assertEquals([new TestReference('unit', 'Sample\PassingTest::testPass'), new TestReference('unit', 'Sample\PassingTest::testData')], $item->tests);
        self::assertSame(['SPEC-001'], $item->related);
        self::assertSame(['parser', 'positions'], $item->labels);
        self::assertSame('Reading', $item->category);
        self::assertSame('original', $item->origin);
        self::assertSame('Support editors.', $item->reason);
        self::assertSame('specs/reader.yaml', $item->file);
        self::assertSame($data, $item->data);
    }

    public function testFromReadsARequirementQuotingItsSource(): void
    {
        $item = Item::from(['id' => 'REQ-1', 'kind' => 'requirement', 'statement' => 'Names start with letters', 'evidence' => [['selector' => '#a', 'quote' => 'Names shall start with a letter.']]], new Source('manual', 'source.html', 'html', 'main p'), 'definition.yaml');
        self::assertSame('requirement', $item->kind);
        self::assertSame('Names start with letters', $item->statement);
    }

    public function testFromReadsRequirementLinks(): void
    {
        $item = Item::from(['id' => 'SPEC-3', 'statement' => 'The reader shall read.', 'requirements' => ['REQ-1', 'REQ-2']], null, 'definition.yaml');
        self::assertSame(['REQ-1', 'REQ-2'], $item->requirements);
    }

    #[DataProvider('providerFromRejectsInvalidItems')]
    public function testFromRejectsInvalidItems(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        Item::from($value, new Source('manual', 'source.html', 'html', 'main p'), 'definition.yaml');
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerFromRejectsInvalidItems(): array
    {
        $item = ['id' => 'SPEC-1', 'statement' => 'The reader shall read.', 'evidence' => [['selector' => '#a', 'quote' => 'Names shall start with a letter.']]];
        return [
            'not a mapping' => ['SPEC-1', 'item must be a mapping.'],
            'unknown field' => [[...$item, 'owner' => 'me'], "item: unknown field 'owner'."],
            'missing id' => [['statement' => 'The reader shall read.'], 'id must be a nonempty string.'],
            'missing statement' => [['id' => 'SPEC-1'], 'statement must be a nonempty string.'],
            'blank kind' => [[...$item, 'kind' => ''], 'kind must be a nonempty string.'],
            'blank status' => [[...$item, 'status' => ' '], 'status must be a nonempty string.'],
            'evidence not a list' => [[...$item, 'evidence' => ['selector' => '#a']], 'evidence must be a list.'],
            'invalid evidence' => [[...$item, 'evidence' => [['selector' => '#a']]], 'quote must be a nonempty string.'],
            'tests not a list' => [[...$item, 'tests' => 'unit'], 'tests must be a list.'],
            'invalid test' => [[...$item, 'tests' => [['runner' => 'unit']]], 'target must be a nonempty string.'],
            'duplicate requirements' => [[...$item, 'requirements' => ['REQ-1', 'REQ-1']], 'requirements contains duplicates.'],
            'related not a list' => [[...$item, 'related' => 'SPEC-2'], 'related must be a list.'],
            'blank label' => [[...$item, 'labels' => ['']], 'labels must contain nonempty strings.'],
            'blank category' => [[...$item, 'category' => ''], 'category must be a nonempty string.'],
            'blank origin' => [[...$item, 'origin' => ' '], 'origin must be a nonempty string.'],
            'blank reason' => [[...$item, 'reason' => ''], 'reason must be a nonempty string.'],
            'invalid id' => [[...$item, 'id' => '1-SPEC'], 'Item IDs must start with a letter and contain letters, digits, dots, underscores or dashes.'],
            'statement breaking EARS' => [[...$item, 'statement' => 'The reader will read.'], 'SPEC-1: EARS: expected The <system name> shall <system response>.'],
            'invalid design' => [[...$item, 'design' => [[]]], 'SPEC-1: a design reference needs url or text.'],
        ];
    }
}
