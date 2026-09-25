<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DocumentReader;
use Requirements\Config\Loader;
use Requirements\Config\MarkdownDocument;
use Requirements\Console\Formatter;
use Requirements\Input\InvalidInputException;
use stdClass;
use Symfony\Component\Process\Process;
use Tests\Support\Workspace;

final class MarkdownTest extends TestCase
{
    public function testYamlAndMarkdownShareTheModelAndPreserveLinksAndBadgesDuringFormatting(): void
    {
        $workspace = new Workspace();
        $yaml = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md', 'reference.yaml'], 'markdown' => ['experimental' => true]]);
        $workspace->write('reference.yaml', ['version' => 1, 'source' => ['id' => 'reference', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => [['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'Names may contain digits.', 'evidence' => [['selector' => '#b', 'quote' => 'Names may contain digits.']]]]]);
        $markdown = <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# SPEC-001

When a name is read, the parser shall require a leading letter.

**evidence**

- **selector:** #a

  > Names shall start with a letter.

**requirements**

- [REQ-001](reference.yaml#req-001)

**labels**

![grammar](assets/grammar.svg) ![strictness](https://example.invalid/badge.svg)

**metadata**

- **owner:** Parser team
- **reviewed:** true
- **priority:** 2
- **text:** "true"
- **values:**
  - stable
  - 1

**design**

- [Parser design](https://example.org/design)
- Preserve the original source spelling.
MD;
        file_put_contents($workspace->directory . '/definition.md', $markdown);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $item = $project->items['SPEC-001'];
        self::assertSame($yaml->items['SPEC-001']->statement, $item->statement);
        self::assertSame($yaml->items['SPEC-001']->data['evidence'], $item->data['evidence']);
        self::assertSame(['REQ-001'], $item->requirements);
        self::assertSame(['grammar', 'strictness'], $item->labels);
        self::assertSame(['owner' => 'Parser team', 'reviewed' => true, 'priority' => 2, 'text' => 'true', 'values' => ['stable', 1]], $item->data['metadata']);
        $formatter = new Formatter();
        $formatter->format($project->files, false, $project->markdown);
        $formatted = file_get_contents($workspace->directory . '/definition.md');
        self::assertIsString($formatted);
        self::assertStringContainsString('reference.yaml#req-001', $formatted);
        self::assertStringContainsString('assets/grammar.svg', $formatted);
        self::assertStringNotContainsString('```', $formatted);
        self::assertEquals($item->data, (new Loader())->load($workspace->directory . '/requirements.yaml')->items['SPEC-001']->data);
        self::assertSame([], $formatter->format($project->files, true, $project->markdown));
    }

    public function testTheCliChecksMarkdownAndRunsItsTestWithoutAnExternalMarkdownTool(): void
    {
        $workspace = new Workspace();
        $package = dirname(__DIR__, 2);
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true], 'runners' => ['unit' => ['extension' => 'phpunit', 'command' => [PHP_BINARY, $package . '/vendor/bin/phpunit', '--no-configuration', $package . '/tests/Fixtures/PassingTest.php']]]]);
        file_put_contents($workspace->directory . '/definition.md', <<<'MD'
---
version: 1
source: null
---

# ORIGINAL-001

The converter shall uppercase letters.

**origin**

original

**reason**

Demonstrate the runner contract.

**tests**

- **unit:** Tests\Fixtures\PassingTest::testPass
MD);
        foreach (['lint', 'check', 'spec', 'format'] as $command) {
            $process = new Process([PHP_BINARY, $package . '/bin/requirements', $command, '--json'], $workspace->directory, ['PATH' => '/nonexistent']);
            self::assertSame(0, $process->run(), $process->getOutput() . $process->getErrorOutput());
            self::assertStringContainsString('"passed": true', $process->getOutput());
        }
    }

    #[DataProvider('malformedMarkdown')]
    public function testRejectsInvalidStructureAndFields(string $body): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/definition.md', "---\nversion: 1\nsource: null\n---\n\n" . $body);
        $this->expectException(InvalidInputException::class);
        (new DocumentReader())->read($workspace->directory . '/definition.md', 'definition', ['experimental' => true]);
    }

    /** @return array<string, array{string}> */
    public static function malformedMarkdown(): array
    {
        return [
            'setext heading' => ["SPEC-001\n========\n\nThe reader shall emit a tree.\n"],
            'prose link' => ["# SPEC-001\n\nThe reader shall emit [a tree](design.md).\n"],
            'nested heading' => ["## SPEC-001\n\nThe reader shall emit a tree.\n"],
            'missing statement' => ["# SPEC-001\n\n**reason**\n\nAvoid silent data loss.\n"],
            'extra paragraph' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\nAnother paragraph.\n"],
            'code fence' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n```yaml\norigin: original\n```\n"],
            'unknown field' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**lables**\n\n- grammar\n"],
            'duplicate field' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**origin**\n\noriginal\n\n**origin**\n\noriginal\n"],
            'evidence without selector' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**evidence**\n\n> Original text.\n"],
            'evidence without quotation' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**evidence**\n\n- **selector:** #a\n"],
            'ordered list' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**labels**\n\n1. grammar\n"],
            'reference without link' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**requirements**\n\n- REQ-001\n"],
            'empty label image' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**labels**\n\n![](badge.svg)\n"],
            'nested code' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**metadata**\n\n- **example:**\n\n  ```yaml\n  x: y\n  ```\n"],
            'raw HTML' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n<div>unsupported</div>\n"],
        ];
    }

    public function testAReferenceLinkMustPointToItsLoadedDefinition(): void
    {
        $workspace = new Workspace();
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['*.md', 'definition.yaml'], 'markdown' => ['experimental' => true]]);
        file_put_contents($workspace->directory . '/other.md', <<<'MD'
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
        (new Loader())->load($workspace->directory . '/requirements.yaml');
    }

    public function testExperimentalOptInIsRequired(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('experimental');
        (new DocumentReader())->read('unread.md', 'definition');
    }

    public function testEmptyListsAndStructuredMetadataSurviveRendering(): void
    {
        $workspace = new Workspace();
        $data = (object) ['version' => 1, 'source' => null, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall preserve `shall` literally.', 'origin' => 'original', 'reason' => 'Keep keywords.', 'labels' => [], 'metadata' => (object) ['empty' => new stdClass(), 'array' => [], 'quote' => 'true', 'nested' => [(object) ['value' => null]], 'zero' => 0, 'entities' => 'Keep &copy; and <tokens> literally.', 'numbered' => '1. Entry', 'bullet' => '- Entry', 'lines' => "a\nb"]]]];
        $file = $workspace->directory . '/definition.md';
        $markdown = new MarkdownDocument();
        file_put_contents($file, $markdown->render($data));
        self::assertEquals($data, $markdown->read($file, ['experimental' => true]));
    }
}
