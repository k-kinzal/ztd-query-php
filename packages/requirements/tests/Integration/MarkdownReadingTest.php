<?php

declare(strict_types=1);

namespace Requirements\Tests\Integration;

use InvalidArgumentException;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DocumentReader;
use Requirements\Config\Loader;
use Requirements\Config\MarkdownDocument;
use Requirements\Console\Formatter;
use Requirements\Report\Analyzer;
use Requirements\Tests\Support\Workspace;
use Symfony\Component\DomCrawler\Crawler;

final class MarkdownReadingTest extends TestCase
{
    public function testReadableCardsPreserveTheYamlModelAndRenderOnlyReaderFacingInformation(): void
    {
        $package = dirname(__DIR__, 2);
        $yaml = (new Loader())->load($package . '/examples/requirements.yaml');
        $markdown = (new Loader())->load($package . '/examples/markdown/requirements.yaml');
        foreach ($yaml->items as $id => $item) {
            self::assertEquals($item->data, $markdown->items[$id]->data);
        }
        self::assertSame([], (new Analyzer())->analyze($markdown)->errors);
        $text = file_get_contents($package . '/examples/markdown/grammar.md');
        self::assertIsString($text);
        $body = preg_replace('/\A---\n.*?\n---\n/s', '', $text);
        self::assertIsString($body);
        $html = (string) (new CommonMarkConverter())->convert($body);
        $document = new Crawler($html);
        self::assertSame(['requirement', 'lexical', 'grammar', 'unsupported'], $document->filter('img')->extract(['alt']));
        self::assertSame(['A name starts with a letter.', 'The generator produces C code.'], $document->filter('blockquote p')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame(['../source.html#names', '#req-001', '../source.html#generation'], $document->filter('a')->extract(['href']));
        self::assertStringNotContainsString('selector:', $document->text());
        self::assertStringNotContainsString('**kind**', $body);
        self::assertStringNotContainsString('**evidence**', $body);
        self::assertStringNotContainsString('**labels**', $body);
        self::assertSame([], (new Formatter())->format($markdown->files, true, $markdown->markdown));
    }

    public function testTheProposedSelectorCommentAndReasonSpellingAreAccepted(): void
    {
        $workspace = new Workspace();
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        file_put_contents($workspace->directory . '/definition.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# GENERATOR-001

![unsupported](<https://img.shields.io/badge/status-unsupported-blue>)

The generator shall produce C code.

> <!-- **selector:** \#c -->
> The generator shall produce C code.

**unsupport reason**

This library reads grammars and does not generate C code.
MD);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        self::assertSame('unsupported', $project->items['GENERATOR-001']->status);
        self::assertSame('#c', $project->items['GENERATOR-001']->evidence[0]->selector);
        self::assertSame([], (new Analyzer())->analyze($project)->errors);
        (new Formatter())->format($project->files, false, $project->markdown);
        $formatted = file_get_contents($workspace->directory . '/definition.md');
        self::assertIsString($formatted);
        self::assertStringContainsString('[Source](source.html#c)', $formatted);
        self::assertStringContainsString('**unsupported reason**', $formatted);
        self::assertStringContainsString('status-unsupported-blue', $formatted);
    }

    public function testCitationsResolveFromTheMarkdownFileAndMultipleQuotationsRemainSeparate(): void
    {
        $workspace = new Workspace();
        mkdir($workspace->directory . '/definitions');
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definitions/*.md'], 'markdown' => ['experimental' => true]]);
        file_put_contents($workspace->directory . '/definitions/names.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# SPEC-001

![lexical](../assets/lexical.svg "category")
![grammar](../assets/grammar.svg)

When a name is read, the parser shall require a leading letter.

> Names shall start with a letter.

[Names](../source.html#a)

> Names may contain digits.
>
> [Digits](../source.html#:~:text=Names%20may%20contain%20digits.)
MD);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $item = $project->items['SPEC-001'];
        self::assertSame('lexical', $item->category);
        self::assertSame(['grammar'], $item->labels);
        self::assertCount(2, $item->evidence);
        self::assertSame('#a', $item->evidence[0]->selector);
        self::assertSame('#:~:text=Names%20may%20contain%20digits.', $item->evidence[1]->selector);
        self::assertSame([], (new Analyzer())->analyze($project)->errors);
        $formatter = new Formatter();
        $formatter->format($project->files, false, $project->markdown);
        $formatted = file_get_contents($workspace->directory . '/definitions/names.md');
        self::assertIsString($formatted);
        self::assertStringContainsString('[Names](../source.html#a)', $formatted);
        self::assertStringContainsString('[Digits](../source.html#:~:text=Names%20may%20contain%20digits.)', $formatted);
        self::assertStringContainsString('![lexical](../assets/lexical.svg "category")', $formatted);
        self::assertEquals($item->data, (new Loader())->load($workspace->directory . '/requirements.yaml')->items['SPEC-001']->data);
        self::assertSame([], $formatter->format($project->files, true, $project->markdown));
    }

    public function testFormattingAddsTextFragmentNavigationForAComplexCssSelector(): void
    {
        $workspace = new Workspace();
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        file_put_contents($workspace->directory . '/definition.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# SPEC-001

The parser shall require leading letters.

> <!-- selector: main > p:first-child -->
> Names shall start with a letter.
MD);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        (new Formatter())->format($project->files, false, $project->markdown);
        $formatted = file_get_contents($workspace->directory . '/definition.md');
        self::assertIsString($formatted);
        self::assertStringContainsString('[Source](source.html#:~:text=Names%20shall%20start%20with%20a%20letter.)', $formatted);
        $after = (new Loader())->load($workspace->directory . '/requirements.yaml');
        self::assertSame('main > p:first-child', $after->items['SPEC-001']->evidence[0]->selector);
        self::assertSame([], (new Analyzer())->analyze($after)->errors);
    }

    public function testBadgeValuesWithSeparatorsAndUnicodeSurviveFormatting(): void
    {
        $workspace = new Workspace();
        $data = (object) ['version' => 1, 'source' => null, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall preserve names.', 'origin' => 'original', 'reason' => 'Preserve names.', 'category' => '-names', 'labels' => ['-feature', 'under_score', 'with spaces', 'end-', '日本語']]]];
        $file = $workspace->directory . '/definition.md';
        $markdown = new MarkdownDocument();
        file_put_contents($file, $markdown->render($data));
        self::assertEquals($data, $markdown->read($file, ['experimental' => true]));
    }

    #[DataProvider('invalidCards')]
    public function testRejectsAmbiguousOrMisleadingCardData(string $body): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/definition.md', "---\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: main p\n---\n\n# SPEC-001\n\n" . $body);
        $this->expectException(InvalidArgumentException::class);
        (new DocumentReader())->read($workspace->directory . '/definition.md', 'definition', ['experimental' => true]);
    }

    /** @return array<string, array{string}> */
    public static function invalidCards(): array
    {
        return [
            'badge without statement' => ['![requirement](https://img.shields.io/badge/kind-requirement-blue)'],
            'duplicate status' => ["![supported](a.svg \"status\") ![unsupported](b.svg \"status\")\n\nThe reader shall emit a tree."],
            'duplicate label' => ["![parser](a.svg) ![parser](b.svg)\n\nThe reader shall emit a tree."],
            'badge field conflict' => ["![grammar](a.svg \"category\")\n\nThe reader shall emit a tree.\n\n**category**\n\nlexical"],
            'misleading static badge' => ["![unsupported](https://img.shields.io/badge/status-supported-blue)\n\nThe reader shall emit a tree."],
            'wrong hidden text directive' => ["The reader shall emit a tree.\n\n> <!-- selector: #:~:text=Different%20text. -->\n> Names shall start with a letter."],
            'unknown badge role' => ["![value](a.svg \"lable\")\n\nThe reader shall emit a tree."],
            'wrong citation resource' => ["The reader shall emit a tree.\n\n> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Source](different.html#a)"],
            'wrong citation anchor' => ["The reader shall emit a tree.\n\n> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Source](source.html#b)"],
            'wrong citation text' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.\n\n[Source](source.html#:~:text=Different%20text.)"],
            'unsupported fragment range' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.\n\n[Source](source.html#:~:text=Names,letter.)"],
            'quote with no locator' => ["The reader shall emit a tree.\n\n> Names shall start with a letter."],
            'arbitrary comment' => ["The reader shall emit a tree.\n\n> <!-- unknown: value -->\n> Names shall start with a letter."],
            'raw HTML in quote' => ["The reader shall emit a tree.\n\n> <div>Names shall start with a letter.</div>"],
            'comment outside quote' => ["The reader shall emit a tree.\n\n<!-- selector: #a -->"],
            'two citations' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.\n>\n> [Source](source.html#a)\n\n[Source](source.html#a)"],
        ];
    }
}
