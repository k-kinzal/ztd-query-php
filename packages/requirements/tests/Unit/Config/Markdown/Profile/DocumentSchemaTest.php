<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Profile;

use JsonException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Parser\MarkdownParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Profile\AllowedBlocks;
use Requirements\Config\Markdown\Profile\BlockKind;
use Requirements\Config\Markdown\Profile\DocumentSchema;
use Requirements\Config\Markdown\Profile\Occurrences;
use Requirements\Config\Markdown\Profile\SectionBlocks;
use Requirements\Config\Markdown\Profile\TextConstraint;
use Requirements\Config\Markdown\Quotation;
use Requirements\Config\SchemaValidator;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

#[CoversClass(DocumentSchema::class)]
#[UsesClass(AllowedBlocks::class)]
#[UsesClass(BlockKind::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Occurrences::class)]
#[UsesClass(Quotation::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(SectionBlocks::class)]
#[UsesClass(TextConstraint::class)]
#[Small]
final class DocumentSchemaTest extends TestCase
{
    /**
     * @throws CommonMarkException
     * @throws JsonException
     */
    #[DataProvider('providerValidDocuments')]
    public function testValidateAcceptsProfileDocument(string $markdown, stdClass $frontmatter): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        (new DocumentSchema())->validate((new MarkdownParser($environment))->parse($markdown), $frontmatter, 'doc.md');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, stdClass}>
     */
    public static function providerValidDocuments(): array
    {
        return [
            'one item' => ["# SPEC-001\n\nThe reader shall emit a tree.\n", (object) ['version' => 1, 'source' => null]],
            'item with every block kind' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**evidence**\n\n> <!-- selector: #a -->\n> Quoted.\n\n- one\n", (object) ['version' => 1, 'source' => (object) ['id' => 'manual']]],
            'several items' => ["# SPEC-001\n\nFirst.\n\n# SPEC-002\n\nSecond.\n\n# spec.3_x\n\nThird.\n", (object) ['version' => 1, 'source' => null]],
            'frontmatter declaring its schema' => ["# SPEC-001\n\nFirst.\n", (object) ['$schema' => SchemaValidator::BASE . 'definition.document.yaml', 'version' => 1, 'source' => null]],
        ];
    }

    /**
     * @throws CommonMarkException
     * @throws JsonException
     */
    public function testValidateLeavesFrontmatterSchemaInPlace(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $frontmatter = (object) ['$schema' => 'local.json', 'version' => 1, 'source' => null];
        (new DocumentSchema())->validate((new MarkdownParser($environment))->parse("# SPEC-001\n\nFirst.\n"), $frontmatter, 'doc.md');
        self::assertEquals((object) ['$schema' => 'local.json', 'version' => 1, 'source' => null], $frontmatter);
    }

    /**
     * @throws CommonMarkException
     * @throws JsonException
     */
    #[DataProvider('providerInvalidFrontmatter')]
    public function testValidateRejectsFrontmatter(stdClass $frontmatter, string $detail): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessageMatches('~^doc\.md: document-schema frontmatter: \{.*' . preg_quote($detail, '~') . '~');
        (new DocumentSchema())->validate((new MarkdownParser($environment))->parse("# SPEC-001\n\nFirst.\n"), $frontmatter, 'doc.md');
    }

    /**
     * @return array<string, array{stdClass, string}>
     */
    public static function providerInvalidFrontmatter(): array
    {
        return [
            'missing source' => [(object) ['version' => 1], 'The required properties (source) are missing'],
            'missing version' => [(object) ['source' => null], 'The required properties (version) are missing'],
            'wrong version' => [(object) ['version' => 2, 'source' => null], 'const'],
            'source of wrong type' => [(object) ['version' => 1, 'source' => 'manual'], 'source'],
            'unknown key' => [(object) ['version' => 1, 'source' => null, 'items' => []], 'items'],
        ];
    }

    /**
     * @throws CommonMarkException
     * @throws JsonException
     */
    #[DataProvider('providerInvalidBodies')]
    public function testValidateRejectsBody(string $markdown, string $message): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $document = (new MarkdownParser($environment))->parse($markdown);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new DocumentSchema())->validate($document, (object) ['version' => 1, 'source' => null], 'doc.md');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerInvalidBodies(): array
    {
        return [
            'content before the first heading' => ["Introduction.\n\n# SPEC-001\n\nFirst.\n", 'doc.md: document-schema forbids content before the first heading.'],
            'forbidden block before the first heading' => ["```\ncode\n```\n\n# SPEC-001\n\nFirst.\n", 'doc.md: document-schema forbids unsupported blocks; use quotations, paragraphs and bullet lists.'],
            'second level heading' => ["## SPEC-001\n\nFirst.\n", 'doc.md: document-schema requires top-level item ID headings.'],
            'nested heading after an item' => ["# SPEC-001\n\nFirst.\n\n## Details\n\nMore.\n", 'doc.md: document-schema requires top-level item ID headings.'],
            'heading that is not an ID' => ["# 1SPEC\n\nFirst.\n", 'doc.md: document-schema requires top-level item ID headings.'],
            'heading with spaces' => ["# SPEC 001\n\nFirst.\n", 'doc.md: document-schema requires top-level item ID headings.'],
            'no item' => ['', 'doc.md: item sections: document-schema occurrence constraint failed.'],
            'empty first item' => ["# SPEC-001\n\n# SPEC-002\n\nSecond.\n", 'doc.md: section block 1: document-schema occurrence constraint failed.'],
            'empty last item' => ["# SPEC-001\n\nFirst.\n\n# SPEC-002\n", 'doc.md: section block 1: document-schema occurrence constraint failed.'],
            'forbidden block in an item' => ["# SPEC-001\n\nFirst.\n\n1. one\n", 'doc.md: document-schema forbids ordered-list blocks; use quotations, paragraphs and bullet lists.'],
            'misplaced comment in an item' => ["# SPEC-001\n\nFirst.\n\n> <!-- note -->\n> Quoted.\n", 'Only a selector comment is allowed at the start of an evidence quotation.'],
        ];
    }
}
