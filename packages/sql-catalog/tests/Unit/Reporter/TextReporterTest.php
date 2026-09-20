<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\TextReporter;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TextReporter::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Catalog\Resolution::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
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
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
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
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Catalog\FindingRule::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Evaluation\PathSet::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
final class TextReporterTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('text', (new TextReporter())->name());
    }

    public function testDescriptionMentionsWhatItProduces(): void
    {
        self::assertStringContainsString('terminal', (new TextReporter())->description());
    }

    public function testRenderWritesOneTextFile(): void
    {
        $artifacts = (new TextReporter())->render(new Catalog());
        self::assertSame([TextReporter::FILE], $artifacts->names());
        self::assertStringContainsString('0 statement(s)', (string) $artifacts->sole());
    }

    public function testRenderReportsTheFilesThatCouldNotBeRead(): void
    {
        $rendered = (string) (new TextReporter())->render(new Catalog([], [new AnalysisProblem('a.php', 'broken')]))->sole();
        self::assertStringContainsString('! a.php: broken', $rendered);
    }

    public function testEntryLinesShowTheStatementAndItsParameters(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);
        $lines = (new TextReporter())->entryLines($catalog->entries()[0]);
        self::assertStringContainsString('a.php:1', $lines[0]);
        self::assertStringContainsString('SELECT', $lines[2]);
        self::assertStringContainsString('? = 7', $lines[4]);
    }

    public function testEntryLinesShowTheFindings(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT " . $_GET["x"]); }',
        ]);
        $lines = (new TextReporter())->entryLines($catalog->entries()[0]);
        self::assertStringContainsString('[HIGH]', implode("\n", $lines));
    }

    public function testRenderWritesTheWholeReportExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);

        self::assertSame(
            'a.php:1  SELECT  ' . $catalog->entries()[0]->id . "\n"
            . '  in f via pdo.prepare' . "\n"
            . '  SELECT id FROM users WHERE id = ?' . "\n"
            . '  resolved' . "\n"
            . '  ? = 7' . "\n"
            . "\n"
            . '1 statement(s), 1 fully resolved, 0 finding(s), 0 unreadable file(s).' . "\n",
            (string) (new TextReporter())->render($catalog)->sole(),
        );
    }

    public function testEntryLinesAreWrittenExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT " . $_GET["x"]); }',
        ]);
        $entry = $catalog->entries()[0];

        self::assertSame(
            [
                'a.php:1  SELECT  ' . $entry->id,
                '  in f via pdo.query',
                '  SELECT {$}',
                '  external-input',
                '  [MEDIUM] dynamic-sql 1 value(s) are spliced into the statement text rather than bound.',
                '  [HIGH] external-input A value from external input reaches the statement text.',
            ],
            (new TextReporter())->entryLines($entry),
        );
    }

    public function testStatusLineSaysHowFarTheAnalyzerGot(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, string $s): void { $d->query("SELECT " . $s); }',
        ]);

        self::assertSame('incomplete-model; search did not close', (new TextReporter())->statusLine($catalog->entries()[0]));
    }

    public function testStatusLineWarnsWhenTheSearchDidNotClose(): void
    {
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())),
            [],
            [],
            new CallSite('a.php', 1, 'f', 'unreached'),
            [],
            false,
            ['App\\R::find', 'App\\R::run'],
        );

        self::assertSame(
            'incomplete; search did not close; alternatives may be unreachable; via App\\R::find -> App\\R::run',
            (new TextReporter())->statusLine($entry),
        );
    }

    public function testSummaryLineCountsWhatWasFound(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        self::assertSame(
            '1 statement(s), 1 fully resolved, 0 finding(s), 0 unreadable file(s).',
            (new TextReporter())->summaryLine($catalog),
        );
    }
}
