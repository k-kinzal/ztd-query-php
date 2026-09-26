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
use Requirements\Config\Markdown\Render\MetadataWriter;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

#[CoversClass(MetadataWriter::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Fields::class)]
#[Small]
final class MetadataWriterTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[DataProvider('providerMetadata')]
    public function testWriteWritesNestedLists(mixed $data, string $expected): void
    {
        self::assertSame($expected, (new MetadataWriter())->write($data));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerMetadata(): array
    {
        return [
            'empty mapping' => [new stdClass(), '{}'],
            'empty sequence' => [[], '[]'],
            'scalars' => [(object) ['owner' => 'Parser team', 'reviewed' => true, 'priority' => 2, 'ratio' => 1.5, 'none' => null], "- **owner:** Parser team\n- **reviewed:** true\n- **priority:** 2\n- **ratio:** 1\\.5\n- **none:** null\n"],
            'strings that are JSON text' => [(object) ['text' => 'true', 'null' => 'null', 'number' => '0', 'empty' => ''], "- **text:** \"true\"\n- **null:** \"null\"\n- **number:** \"0\"\n- **empty:** \"\"\n"],
            'sequence' => [['stable', 1], "- stable\n- 1\n"],
            'nested sequence' => [(object) ['values' => ['stable', 1]], "- **values:**\n  - stable\n  - 1\n"],
            'sequence of sequences' => [[['a']], "- []\n  - a\n"],
            'sequence of mappings' => [[(object) ['value' => null]], "- []\n  - **value:** null\n"],
            'empty nested values' => [(object) ['empty' => new stdClass(), 'array' => []], "- **empty:** {}\n- **array:** \\[\\]\n"],
            'empty values in a sequence' => [[new stdClass(), []], "- {}\n- \\[\\]\n"],
            'escaped text' => [(object) ['a*b' => '1. <tokens> & more'], "- **a\\*b:** 1\\. \\<tokens\\> \\& more\n"],
            'unicode and slashes' => [(object) ['path' => ['日本語/a']], "- **path:**\n  - 日本語/a\n"],
            'string with a line break' => [(object) ['lines' => "a\nb"], "- **lines:** a\nb\n"],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testWriteIndentsANestedLevel(): void
    {
        self::assertSame("    - a\n    - **b:** 1\n", (new MetadataWriter())->write(['a'], 4) . (new MetadataWriter())->write((object) ['b' => 1], 4));
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsASequenceThatIsNotAList(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('metadata sequence must be a list.');
        (new MetadataWriter())->write(['a' => 1]);
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsAScalarAtTheTop(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('metadata sequence must be a list.');
        (new MetadataWriter())->write('text');
    }

    /**
     * @throws JsonException
     */
    public function testWriteRejectsAValueJsonCannotEncode(): void
    {
        $this->expectException(JsonException::class);
        (new MetadataWriter())->write([NAN]);
    }
}
