<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\HtmlReporter;

#[CoversClass(HtmlReporter::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(\SqlCatalog\Analysis\BodyWalker::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Catalog\CallSite::class)]
#[UsesClass(\SqlCatalog\Catalog\CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Catalog\FindingRule::class)]
#[UsesClass(\SqlCatalog\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Extension\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
final class HtmlReporterTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('html', (new HtmlReporter())->name());
    }

    public function testDescriptionMentionsWhatItProduces(): void
    {
        self::assertStringContainsString('HTML', (new HtmlReporter())->description());
    }

    public function testRenderWritesOneSelfContainedPage(): void
    {
        $artifacts = (new HtmlReporter())->render(new Catalog());
        self::assertSame([HtmlReporter::FILE], $artifacts->names());
        $page = (string) $artifacts->sole();
        self::assertStringStartsWith('<!DOCTYPE html>', $page);
        self::assertStringNotContainsString('<script', $page);
    }

    public function testSummaryCountsTheStatements(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        self::assertStringContainsString('1 statement(s)', (new HtmlReporter())->summary($catalog));
    }

    public function testRowCarriesTheStatementAndWhereItIsIssued(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT id FROM users"); }',
        ]);
        $row = (new HtmlReporter())->row($catalog->entries()[0]);
        self::assertStringContainsString('SELECT id FROM users', $row);
        self::assertStringContainsString('a.php:1', $row);
        self::assertStringContainsString('users', $row);
    }

    public function testValuesListTheBoundParameters(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);
        $reporter = new HtmlReporter();
        self::assertStringContainsString('<li>', $reporter->values($catalog->entries()[0]));
        self::assertStringContainsString('7', $reporter->values($catalog->entries()[0]));
    }

    public function testValuesSayWhenThereAreNone(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        self::assertStringContainsString('none', (new HtmlReporter())->values($catalog->entries()[0]));
    }

    public function testFindingsCarryTheirSeverity(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT " . $_GET["x"]); }',
        ]);
        self::assertStringContainsString('badge high', (new HtmlReporter())->findings($catalog->entries()[0]));
    }

    public function testFindingsSayWhenThereAreNone(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        self::assertStringContainsString('none', (new HtmlReporter())->findings($catalog->entries()[0]));
    }

    public function testProblemsAreListedOnlyWhenThereAreSome(): void
    {
        $reporter = new HtmlReporter();
        self::assertSame('', $reporter->problems(new Catalog()));
        self::assertStringContainsString('a.php', $reporter->problems(new Catalog([], [new AnalysisProblem('a.php', 'broken')])));
    }

    public function testStylesAreCarriedWithThePage(): void
    {
        self::assertStringContainsString('badge', (new HtmlReporter())->styles());
    }

    public function testRowIsWrittenExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);

        self::assertSame(
            '<tr class="sev-info"><td><span class="kind">SELECT</span><pre>SELECT id FROM users WHERE id = ?</pre><p class="tables">users</p></td><td><code>a.php:1</code><p>f</p><p class="sink">pdo.prepare</p></td><td><ul><li><code>?</code> 7</li></ul></td><td><span class="none">none</span></td></tr>',
            (new HtmlReporter())->row($catalog->entries()[0]),
        );
    }

    public function testValuesAreWrittenExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);

        self::assertSame('<ul><li><code>?</code> 7</li></ul>', (new HtmlReporter())->values($catalog->entries()[0]));
    }

    public function testFindingsAreWrittenExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT " . $_GET["x"]); }',
        ]);

        self::assertSame(
            '<ul><li><span class="badge medium">medium</span> 1 value(s) are spliced into the statement text rather than bound.</li>'
            . '<li><span class="badge high">high</span> A value from external input reaches the statement text.</li></ul>',
            (new HtmlReporter())->findings($catalog->entries()[0]),
        );
    }

    public function testSummaryIsWrittenExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);

        self::assertSame(
            '<p class="summary">1 statement(s) &middot; 1 fully resolved &middot; 0 finding(s)</p>',
            (new HtmlReporter())->summary($catalog),
        );
    }

    public function testProblemsAreWrittenExactly(): void
    {
        self::assertSame(
            '<h2>Not analyzed</h2><ul class="problems"><li><code>b.php</code> broken</li></ul>',
            (new HtmlReporter())->problems(new Catalog([], [new AnalysisProblem('b.php', 'broken')])),
        );
    }

    public function testStylesAreWrittenExactly(): void
    {
        self::assertSame('body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:2rem;color:#111}h1{font-size:1.4rem}.summary{color:#555}table{border-collapse:collapse;width:100%}th,td{border-top:1px solid #ddd;padding:.6rem;text-align:left;vertical-align:top}th{background:#f6f8fa;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em}pre{margin:.3rem 0;white-space:pre-wrap;word-break:break-word;font-size:.85rem}code{font-size:.85rem}ul{margin:0;padding-left:1.1rem}.kind{font-size:.7rem;font-weight:700;color:#57606a}.tables,.sink{color:#57606a;font-size:.8rem;margin:.2rem 0}.none{color:#8c959f}.badge{display:inline-block;padding:0 .4rem;border-radius:.6rem;font-size:.7rem;color:#fff;background:#57606a}.badge.high{background:#cf222e}.badge.medium{background:#bf8700}.badge.low{background:#0969da}tr.sev-high{background:#fff5f5}', (new HtmlReporter())->styles());
    }

    public function testRenderWritesTheWholePageExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);

        self::assertSame(
            '<!DOCTYPE html>' . "\n"
            . '<html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>SQL catalog</title><style>body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;'
            . 'margin:2rem;color:#111}h1{font-size:1.4rem}.summary{color:#555}table{border-collapse:collapse;'
            . 'width:100%}th,td{border-top:1px solid #ddd;padding:.6rem;text-align:left;vertical-align:top}'
            . 'th{background:#f6f8fa;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em}'
            . 'pre{margin:.3rem 0;white-space:pre-wrap;word-break:break-word;font-size:.85rem}'
            . 'code{font-size:.85rem}ul{margin:0;padding-left:1.1rem}'
            . '.kind{font-size:.7rem;font-weight:700;color:#57606a}'
            . '.tables,.sink{color:#57606a;font-size:.8rem;margin:.2rem 0}'
            . '.none{color:#8c959f}'
            . '.badge{display:inline-block;padding:0 .4rem;border-radius:.6rem;font-size:.7rem;color:#fff;background:#57606a}'
            . '.badge.high{background:#cf222e}.badge.medium{background:#bf8700}.badge.low{background:#0969da}'
            . 'tr.sev-high{background:#fff5f5}</style></head><body>'
            . '<h1>SQL catalog</h1>'
            . '<p class="summary">1 statement(s) &middot; 1 fully resolved &middot; 0 finding(s)</p>'
            . '<table><thead><tr><th>Statement</th><th>Where</th><th>Values</th><th>Findings</th></tr></thead>'
            . '<tbody><tr class="sev-info"><td><span class="kind">SELECT</span><pre>SELECT 1</pre>'
            . '<p class="tables"></p></td><td><code>a.php:1</code><p>f</p><p class="sink">pdo.query</p></td>'
            . '<td><span class="none">none</span></td><td><span class="none">none</span></td></tr>'
            . '</tbody></table>'
            . '</body></html>' . "\n",
            (string) (new HtmlReporter())->render($catalog)->sole(),
        );
    }

    public function testRenderCarriesTheFilesThatCouldNotBeRead(): void
    {
        $rendered = (string) (new HtmlReporter())
            ->render(new Catalog([], [new AnalysisProblem('b.php', 'broken')]))
            ->sole();

        self::assertStringContainsString(
            '<h2>Not analyzed</h2><ul class="problems"><li><code>b.php</code> broken</li></ul></body></html>',
            $rendered,
        );
    }

    public function testEscapeMakesTextSafeToPlaceInTheDocument(): void
    {
        self::assertSame('&lt;b&gt;&amp;&#039;', (new HtmlReporter())->escape("<b>&'"));
    }
}
