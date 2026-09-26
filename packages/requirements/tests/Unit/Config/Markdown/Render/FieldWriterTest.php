<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Render;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Render\FieldWriter;
use Requirements\Config\Markdown\Render\MetadataWriter;
use Requirements\Config\Markdown\Render\Record;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

#[CoversClass(FieldWriter::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Fields::class)]
#[UsesClass(MetadataWriter::class)]
#[UsesClass(Record::class)]
#[Small]
final class FieldWriterTest extends TestCase
{
    /**
     * @param array<string, string> $links
     * @throws JsonException
     */
    #[DataProvider('providerFields')]
    public function testWriteWritesTheSectionBody(string $name, mixed $value, array $links, string $expected): void
    {
        self::assertSame($expected, (new FieldWriter())->write($name, $value, $links));
    }

    /**
     * @return array<string, array{string, mixed, array<string, string>, string}>
     */
    public static function providerFields(): array
    {
        return [
            'prose' => ['reason', 'Keep *keywords*.', [], 'Keep \\*keywords\\*.'],
            'metadata' => ['metadata', (object) ['owner' => 'Parser team', 'values' => [1]], [], "- **owner:** Parser team\n- **values:**\n  - 1"],
            'empty metadata' => ['metadata', (object) [], [], '{}'],
            'read requirement link' => ['requirements', ['REQ-001'], ['REQ-001' => 'reference.yaml#req-001'], '- [REQ-001](reference.yaml#req-001)'],
            'new requirement link' => ['requirements', ['REQ.A-1', 'REQ-002'], [], "- [REQ.A-1](#reqa-1)\n- [REQ-002](#req-002)"],
            'related link with a space' => ['related', ['SPEC-001'], ['SPEC-001' => 'other file.md#spec-001'], '- [SPEC-001](<other%20file.md#spec-001>)'],
            'manual tests' => ['tests', [(object) ['runner' => 'fuzz', 'target' => 'slow', 'run' => 'manual']], [], "- **fuzz:** slow\n  - **run:** manual"],
            'automatic tests' => ['tests', [(object) ['runner' => 'unit', 'target' => 'fast', 'run' => 'auto']], [], "- **unit:** fast\n  - **run:** auto"],
            'tests' => ['tests', [(object) ['runner' => 'unit', 'target' => 'Sample\\PassingTest::test_pass']], [], '- **unit:** Sample\\\\PassingTest::test\\_pass'],
            'design link with text' => ['design', [(object) ['url' => 'https://example.org/design', 'text' => 'Parser design']], [], '- [Parser design](https://example.org/design)'],
            'design link without text' => ['design', [(object) ['url' => 'https://example.org/design']], [], '- [https://example.org/design](https://example.org/design)'],
            'design text' => ['design', [(object) ['text' => 'Preserve the original source spelling.']], [], '- Preserve the original source spelling.'],
            'empty list' => ['labels', [], [], 'None.'],
            'empty evidence' => ['evidence', [], [], 'None.'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsAValueThatIsNotAList(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('tests must be a list.');
        (new FieldWriter())->write('tests', 5, []);
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsARecordOfAnotherField(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("Cannot render Markdown field 'evidence'.");
        (new FieldWriter())->write('evidence', [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']], []);
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsAStringEntryOutsideTheReferenceFields(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected a Markdown record.');
        (new FieldWriter())->write('labels', ['grammar'], []);
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsATestWithoutATarget(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('target must be a nonempty string.');
        (new FieldWriter())->write('tests', [(object) ['runner' => 'unit']], []);
    }
}
