<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\Source\SourceCode;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(SourceCode::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class SourceCodeTest extends TestCase
{
    public function testExcerptShowsContextAndLinksToTheHighlightedCallInTheFullFile(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 12, 'f', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry], [], ['src/a.php' => implode("\n", range(1, 30))]));
        $html = (new SourceCode())->excerpt($site, $entry);

        self::assertStringContainsString('href="../files/src-a-php.html#L12">View full source</a>', $html);
        self::assertStringContainsString('class="source-line source-call" id="L12"', $html);
        self::assertStringContainsString('id="L4"', $html);
        self::assertStringContainsString('id="L20"', $html);
        self::assertStringNotContainsString('id="L3"', $html);
        self::assertStringNotContainsString('id="L21"', $html);
    }

    #[DataProvider('providerBoundaryLines')]
    public function testExcerptClipsAtTheStartAndEndOfTheFile(int $line): void
    {
        $source = "<?php\n\t\$db->query(\n\t\t\$sql\n\t);";
        $entry = new CatalogEntry('a', StatementKind::Unknown, TextPattern::fromText(''), [], [], new CallSite('a.php', $line, 'f', 'pdo.query'), []);
        $html = (new SourceCode())->excerpt(new ReportSite(new Catalog([$entry], [], ['a.php' => $source])), $entry);
        self::assertStringContainsString('id="L1"', $html);
        self::assertStringContainsString('id="L4"', $html);
        self::assertStringNotContainsString('id="L0"', $html);
        self::assertStringNotContainsString('id="L5"', $html);
        self::assertStringContainsString("\t\$db-&gt;query(", $html);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerBoundaryLines(): iterable
    {
        yield 'first line' => [1];
        yield 'last line' => [4];
    }

    public function testExcerptOmitsUnavailableSourceWithoutReadingTheFilesystem(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite(__FILE__, 1, 'f', 'pdo.query'), []);
        self::assertSame('', (new SourceCode())->excerpt(new ReportSite(new Catalog([$entry])), $entry));
    }

    public function testFileIncludesAllLinesAndMarksEveryCall(): void
    {
        $first = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []);
        $last = new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 30, 'f', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$first, $last], [], ['a.php' => implode("\n", range(1, 30))]));
        $html = (new SourceCode())->file($site, 'a.php');

        self::assertStringContainsString('id="L1"', $html);
        self::assertStringContainsString('class="source-line source-call" id="L2"', $html);
        self::assertStringContainsString('class="source-line source-call" id="L30"', $html);
        self::assertStringContainsString('href="#L30" aria-label="Line 30"', $html);
        self::assertSame('', (new SourceCode())->file($site, 'missing.php'));
    }

    public function testLinesEscapesMarkupQuotesAndInvalidUtf8WithoutDroppingTheSource(): void
    {
        $html = (new SourceCode())->lines(['</code></pre><script>alert("x")</script>&\'', "\t\xFF"], 7, [8], 'a"b.html');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;/code&gt;&lt;/pre&gt;&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&amp;&#039;', $html);
        self::assertStringContainsString('href="a&quot;b.html#L7"', $html);
        self::assertStringContainsString("\t\u{FFFD}", $html);
    }

    public function testSplitPreservesEmptyLinesAndRecognizesPhpNewlines(): void
    {
        self::assertSame(['a', '', 'b', 'c', ''], (new SourceCode())->split("a\r\n\r\nb\rc\n"));
        self::assertSame([''], (new SourceCode())->split(''));
    }
}
