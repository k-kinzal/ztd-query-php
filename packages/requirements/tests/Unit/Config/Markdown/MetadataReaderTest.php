<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\MetadataReader;
use Requirements\Config\Markdown\Nodes;
use Requirements\Input\InvalidInputException;
use stdClass;
use Tests\Fake\MarkdownNodes;

#[CoversClass(MetadataReader::class)]
#[UsesClass(Nodes::class)]
#[Small]
final class MetadataReaderTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    public function testReadReadsAListOfKeysAndValues(): void
    {
        $markdown = "- **owner:** Parser team\n- **reviewed:** true\n- **priority:** 2\n- **text:** \"true\"\n- **values:**\n  - stable\n  - 1\n";
        self::assertEquals((object) ['owner' => 'Parser team', 'reviewed' => true, 'priority' => 2, 'text' => 'true', 'values' => ['stable', 1]], (new MetadataReader())->read(MarkdownNodes::first($markdown)));
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadReadsAJsonObjectParagraph(): void
    {
        self::assertEquals((object) ['owner' => 'Parser team', 'values' => [1]], (new MetadataReader())->read(MarkdownNodes::first('{"owner": "Parser team", "values": [1]}')));
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadReadsAnEmptyJsonObjectParagraph(): void
    {
        self::assertEquals(new stdClass(), (new MetadataReader())->read(MarkdownNodes::first('{}')));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotMappings')]
    public function testReadRejectsAValueThatIsNotAMapping(string $markdown): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Metadata must be a list of bold keys and values.');
        (new MetadataReader())->read(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotMappings(): array
    {
        return [
            'text' => ['Parser team'],
            'JSON array' => ['[1, 2]'],
            'JSON number' => ['2'],
            'sequence list' => ["- stable\n- 1\n"],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testNestedReadsAMapping(): void
    {
        self::assertEquals((object) ['a' => 1, 'b' => 'text'], (new MetadataReader())->nested(MarkdownNodes::first("- **a:** 1\n- **b:** text\n")));
    }

    /**
     * @throws CommonMarkException
     */
    public function testNestedReadsASequence(): void
    {
        self::assertSame(['stable', 1, null, true], (new MetadataReader())->nested(MarkdownNodes::first("- stable\n- 1\n- null\n- true\n")));
    }

    /**
     * @throws CommonMarkException
     */
    public function testNestedReadsNestedSequencesUnderAnEmptyListValue(): void
    {
        self::assertSame([['a', 'b'], []], (new MetadataReader())->nested(MarkdownNodes::first("- []\n  - a\n  - b\n- []\n")));
    }

    /**
     * @throws CommonMarkException
     */
    public function testNestedReadsNestedMappings(): void
    {
        self::assertEquals((object) ['outer' => (object) ['inner' => [(object) ['value' => null]]]], (new MetadataReader())->nested(MarkdownNodes::first("- **outer:**\n  - **inner:**\n    - []\n      - **value:** null\n")));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedLists')]
    public function testNestedRejectsMalformedLists(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new MetadataReader())->nested(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedLists(): array
    {
        return [
            'ordered list' => ['1. a', 'Expected a bullet list.'],
            'quotation item' => ['- > a', 'Expected a metadata key or value.'],
            'key after value' => ["- a\n- **b:** 1\n", 'Do not mix mapping keys and sequence values in one metadata list.'],
            'value after key' => ["- **a:** 1\n- b\n", 'Do not mix mapping keys and sequence values in one metadata list.'],
            'nested list under a key with a value' => ["- **a:** 1\n  - b\n", 'Nested metadata must have an empty parent value and one nested list.'],
            'nested list under a value' => ["- x\n  - b\n", 'Nested metadata must have an empty parent value and one nested list.'],
            'nested list under an empty list key' => ["- **a:** []\n  - b\n", 'Nested metadata must have an empty parent value and one nested list.'],
            'two nested blocks' => ["- **a:**\n\n  - b\n\n  c\n", 'Nested metadata must have an empty parent value and one nested list.'],
            'empty key' => ['- **:** 1', 'Metadata keys must be nonempty and unique.'],
            'duplicate key' => ["- **a:** 1\n- **a:** 2\n", 'Metadata keys must be nonempty and unique.'],
            'empty value' => ['- **a:**', 'Write "" for an empty string or supply a metadata value.'],
            'key without colon' => ['- **a** 1', 'Expected a colon after the bold field name.'],
        ];
    }

    #[DataProvider('providerScalars')]
    public function testScalarDecodesJsonOrKeepsText(string $text, mixed $expected): void
    {
        self::assertEquals($expected, (new MetadataReader())->scalar($text));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function providerScalars(): array
    {
        return [
            'text' => ['Parser team', 'Parser team'],
            'JSON string' => ['"true"', 'true'],
            'empty JSON string' => ['""', ''],
            'boolean' => ['true', true],
            'integer' => ['2', 2],
            'float' => ['1.5', 1.5],
            'null' => ['null', null],
            'empty list' => ['[]', []],
            'object' => ['{"a": 1}', (object) ['a' => 1]],
            'broken JSON' => ['{"a": 1', '{"a": 1'],
        ];
    }

    public function testScalarRejectsAnEmptyValue(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Write "" for an empty string or supply a metadata value.');
        (new MetadataReader())->scalar('');
    }
}
