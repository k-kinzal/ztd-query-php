<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\AnalysisProblem;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Reporter\CatalogArtifacts;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Facade\AnalysisOptions;
use SqlCatalog\Facade\Analyzer;
use SqlCatalog\Reporter\Text\TextReporter;

#[CoversClass(TextReporter::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Resolution::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ValueBinder::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Extension\Doctrine\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Facade\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Mysqli\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Pdo\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Core\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Core\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Core\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\FindingRule::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
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
            . 'Conditions are not evaluated; runtime reachability is not assessed.' . "\n"
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
