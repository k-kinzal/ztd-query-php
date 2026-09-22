<?php

declare(strict_types=1);

namespace Requirements\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DocumentReader;
use Requirements\Config\Loader;
use Requirements\Config\SchemaValidator;
use Requirements\Console\Formatter;
use Requirements\Tests\Support\Workspace;

final class MarkdownTest extends TestCase
{
    public function testYamlAndMarkdownUseTheSameModelAndFormatIdempotently(): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $yaml = $loader->load($workspace->directory . '/requirements.yaml');
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        $source = "id: manual\nuri: source.html\nformat: html\nselector: main p";
        $markdown = "---\n\$schema: " . SchemaValidator::BASE . "definition.document.yaml\nversion: 1\nsource:\n  " . str_replace("\n", "\n  ", $source) . "\n---\n\n# SPEC-001\n\nWhen a name is read, the parser shall require a leading letter.\n\n```yaml\nevidence:\n  - selector: '#a'\n    quote: Names shall start with a letter.\n```\n";
        file_put_contents($workspace->directory . '/definition.md', $markdown);
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        self::assertSame($yaml->items['SPEC-001']->data, $project->items['SPEC-001']->data);
        $before = (new DocumentReader())->read($workspace->directory . '/definition.md', 'definition', $project->markdown);
        $formatter = new Formatter();
        $formatter->format($project->files, false, $project->markdown);
        self::assertEquals($before, (new DocumentReader())->read($workspace->directory . '/definition.md', 'definition', $project->markdown));
        self::assertSame([], $formatter->format($project->files, true, $project->markdown));
    }

    #[DataProvider('malformedMarkdown')]
    public function testRejectsInvalidDocumentStructureAndItemFields(string $body): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/definition.md', "---\nversion: 1\nsource: null\n---\n\n" . $body);
        $this->expectException(InvalidArgumentException::class);
        (new DocumentReader())->read($workspace->directory . '/definition.md', 'definition', ['experimental' => true]);
    }

    /** @return array<string, array{string}> */
    public static function malformedMarkdown(): array
    {
        return [
            'nested heading' => ["## SPEC-001\n\nThe reader shall emit a tree.\n"],
            'missing statement' => ["# SPEC-001\n\n```yaml\norigin: original\nreason: Test\n```\n"],
            'extra paragraph' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\nAnother paragraph.\n"],
            'wrong fence' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n```json\n{}\n```\n"],
            'duplicate identity' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n```yaml\nid: OTHER\n```\n"],
            'unknown metadata' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n```yaml\nlables: [foo]\n```\n"],
            'metadata list' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n```yaml\n- foo\n```\n"],
        ];
    }

    public function testExperimentalOptInIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('experimental');
        (new DocumentReader())->read('unread.md', 'definition');
    }

    public function testMissingReferenceValidatorFailsExplicitly(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/definition.md', '# SPEC-001');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('schematter');
        (new DocumentReader())->read($workspace->directory . '/definition.md', 'definition', ['experimental' => true, 'command' => ['/nonexistent/schematter']]);
    }
}
