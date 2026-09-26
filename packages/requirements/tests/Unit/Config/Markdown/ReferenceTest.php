<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DefinitionReader;
use Requirements\Config\DocumentReader;
use Requirements\Config\JsonSchemaFile;
use Requirements\Config\Loader;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\CardReader;
use Requirements\Config\Markdown\FieldReader;
use Requirements\Config\Markdown\FieldSections;
use Requirements\Config\Markdown\Frontmatter;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Profile\AllowedBlocks;
use Requirements\Config\Markdown\Profile\BlockKind;
use Requirements\Config\Markdown\Profile\DocumentSchema;
use Requirements\Config\Markdown\Profile\Occurrences;
use Requirements\Config\Markdown\Profile\SectionBlocks;
use Requirements\Config\Markdown\Profile\TextConstraint;
use Requirements\Config\Markdown\QuotationBlocks;
use Requirements\Config\Markdown\Reference;
use Requirements\Config\MarkdownDocument;
use Requirements\Config\SchemaValidator;
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
use Tests\Fake\ProjectDirectory;

#[CoversClass(Reference::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(Badges::class)]
#[UsesClass(CardReader::class)]
#[UsesClass(FieldReader::class)]
#[UsesClass(FieldSections::class)]
#[UsesClass(Frontmatter::class)]
#[UsesClass(AllowedBlocks::class)]
#[UsesClass(BlockKind::class)]
#[UsesClass(DocumentSchema::class)]
#[UsesClass(Occurrences::class)]
#[UsesClass(SectionBlocks::class)]
#[UsesClass(TextConstraint::class)]
#[UsesClass(QuotationBlocks::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Wording::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(ItemValidator::class)]
#[UsesClass(Source::class)]
#[UsesClass(Item::class)]
#[UsesClass(Loader::class)]
#[UsesClass(Nodes::class)]
#[Small]
final class ReferenceTest extends TestCase
{
    public function testReferenceHoldsTheLinkedIdTargetAndFile(): void
    {
        $reference = new Reference('REQ-001', 'reference.yaml#req-001', 'definition.md');
        self::assertSame('REQ-001', $reference->id);
        self::assertSame('reference.yaml#req-001', $reference->url);
        self::assertSame('definition.md', $reference->file);
    }

    #[DataProvider('providerLinks')]
    public function testValidateAcceptsALinkToTheDefiningFileAndHeading(string $id, string $file, string $url): void
    {
        $project = new ProjectDirectory();
        $project->put('definitions/cards.md', '');
        $project->put('definitions/other file.md', '');
        $items = ['REQ-001' => new Item('REQ-001', 'requirement', 'A name starts with a letter.', 'supported', null, [], [], [], [], [], '', 'original', 'Why.', $project->path('definitions/other file.md'), []), 'SPEC.A-1' => new Item('SPEC.A-1', 'specification', 'The reader shall emit a tree.', 'supported', null, [], [], [], [], [], '', 'original', 'Why.', $project->path('definition.yaml'), [])];
        (new Reference($id, $url, $project->path($file)))->validate($items);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function providerLinks(): array
    {
        return [
            'same file' => ['REQ-001', 'definitions/other file.md', '#req-001'],
            'same file with an uppercase fragment' => ['REQ-001', 'definitions/other file.md', '#REQ-001'],
            'same file without a fragment' => ['REQ-001', 'definitions/other file.md', ''],
            'sibling file' => ['REQ-001', 'definitions/cards.md', 'other%20file.md#req-001'],
            'sibling file without a fragment' => ['REQ-001', 'definitions/cards.md', 'other%20file.md'],
            'parent directory' => ['SPEC.A-1', 'definitions/cards.md', '../definition.yaml#speca-1'],
            'percent-encoded fragment' => ['SPEC.A-1', 'definition.yaml', 'definition.yaml#spec%61-1'],
        ];
    }

    #[DataProvider('providerRemoteLinks')]
    public function testValidateRejectsALinkThatIsNotLocal(string $url): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("definition.md: reference 'REQ-001' must link to a local definition file.");
        (new Reference('REQ-001', $url, 'definition.md'))->validate([]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerRemoteLinks(): array
    {
        return [
            'scheme' => ['https://example.org/reference.yaml#req-001'],
            'host' => ['//example.org/reference.yaml#req-001'],
            'query' => ['reference.yaml?x=1#req-001'],
            'malformed' => ['http:///reference.yaml'],
        ];
    }

    public function testValidateRejectsALinkToAnUnknownItem(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.md') . ": link to 'REQ-001' does not point to its loaded definition file.");
        (new Reference('REQ-001', 'definition.yaml#req-001', $project->path('definition.md')))->validate([]);
    }

    public function testValidateRejectsALinkToAnotherFile(): void
    {
        $project = new ProjectDirectory();
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The reader shall emit a tree.', 'supported', null, [], [], [], [], [], '', 'original', 'Why.', $project->path('definition.yaml'), [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("link to 'SPEC-001' does not point to its loaded definition file.");
        (new Reference('SPEC-001', 'source.html#spec-001', $project->path('definition.md')))->validate($items);
    }

    public function testValidateRejectsALinkToAMissingFile(): void
    {
        $project = new ProjectDirectory();
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The reader shall emit a tree.', 'supported', null, [], [], [], [], [], '', 'original', 'Why.', $project->path('definition.yaml'), [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("link to 'SPEC-001' does not point to its loaded definition file.");
        (new Reference('SPEC-001', 'missing.yaml#spec-001', $project->path('definition.md')))->validate($items);
    }

    public function testValidateRejectsTheWrongHeadingFragment(): void
    {
        $project = new ProjectDirectory();
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The reader shall emit a tree.', 'supported', null, [], [], [], [], [], '', 'original', 'Why.', $project->path('definition.yaml'), [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.md') . ": link to 'SPEC-001' has the wrong heading fragment.");
        (new Reference('SPEC-001', 'definition.yaml#spec-002', $project->path('definition.md')))->validate($items);
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsALinkToAnotherLoadedDefinitionFile(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['*.md', 'definition.yaml'], 'markdown' => ['experimental' => true]]);
        $project->put('other.md', <<<'MD'
---
version: 1
source: null
---

# OTHER-001

The reader shall reject truncated input.

**origin**

original

**reason**

Avoid silent data loss.

**related**

- [SPEC-001](other.md#spec-001)
MD);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('does not point to its loaded definition file');
        (new Loader())->load($project->path('requirements.yaml'));
    }
}
