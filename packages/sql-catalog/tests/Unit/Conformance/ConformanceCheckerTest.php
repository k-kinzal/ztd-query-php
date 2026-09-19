<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Conformance\ConformanceChecker;
use SqlCatalog\Conformance\ConformanceReport;
use SqlCatalog\Conformance\ObservedStatement;
use SqlCatalog\Conformance\PatternMatcher;

#[CoversClass(ConformanceChecker::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(ConformanceReport::class)]
#[UsesClass(ObservedStatement::class)]
#[UsesClass(PatternMatcher::class)]
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
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Catalog\FindingRule::class)]
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
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
final class ConformanceCheckerTest extends TestCase
{
    public function testCheckReportsAStatementTheCatalogDescribes(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        $report = (new ConformanceChecker())->check($catalog, [new ObservedStatement('SELECT 1')]);
        self::assertTrue($report->isSound());
        self::assertSame(1, $report->resolved);
    }

    public function testCheckReportsAStatementTheCatalogMissed(): void
    {
        $report = (new ConformanceChecker())->check(new Catalog(), [new ObservedStatement('SELECT 1')]);
        self::assertFalse($report->isSound());
        self::assertCount(1, $report->uncovered);
    }

    public function testCheckOfNothingObservedIsSound(): void
    {
        self::assertTrue((new ConformanceChecker())->check(new Catalog(), [])->isSound());
    }

    public function testCheckRejectsAValueNoMatchingStatementAdmits(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM t WHERE a = ?"); $s->execute([1]); }',
        ]);
        $observed = new ObservedStatement('SELECT id FROM t WHERE a = ?', [99], [], 'a.php');
        self::assertFalse((new ConformanceChecker())->check($catalog, [$observed])->isSound());
    }

    public function testMatchingFindsEveryStatementThatDescribesTheObservation(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        self::assertCount(1, (new ConformanceChecker())->matching($catalog, new ObservedStatement('SELECT 1')));
        self::assertSame([], (new ConformanceChecker())->matching($catalog, new ObservedStatement('SELECT 2')));
    }

    public function testHasResolvedLooksForAStatementWithoutGaps(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, string $n) { $d->query("SELECT " . $n); }',
        ]);
        $checker = new ConformanceChecker();
        self::assertFalse($checker->hasResolved($catalog->entries()));
        self::assertFalse($checker->hasResolved([]));
    }

    public function testValueMismatchesNameTheParameterAndTheValue(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM t WHERE a = ?"); $s->execute([1]); }',
        ]);
        $observed = new ObservedStatement('SELECT id FROM t WHERE a = ?', [99], [], 'a.php');
        $mismatches = (new ConformanceChecker())->valueMismatches($catalog->entries(), $observed);
        self::assertStringContainsString('parameter 0', $mismatches[0]);
        self::assertStringContainsString('99', $mismatches[0]);
    }

    public function testBoundValuesKeyNamedParametersWithoutTheirColon(): void
    {
        $observed = new ObservedStatement('SELECT 1', ['a'], [':id' => 1]);
        self::assertSame([0 => 'a', 'id' => 1], (new ConformanceChecker())->boundValues($observed));
    }

    public function testAnyAdmitsAcceptsAKeyNoStatementUses(): void
    {
        self::assertTrue((new ConformanceChecker())->anyAdmits([], '0', 'anything'));
    }

    public function testIsKeyedMatchesByNameOrByPosition(): void
    {
        $checker = new ConformanceChecker();
        self::assertTrue($checker->isKeyed(new Placeholder(':id', 0, 'id', null), 'id'));
        self::assertTrue($checker->isKeyed(new Placeholder(':id', 0, 'id', null), '0'));
        self::assertFalse($checker->isKeyed(new Placeholder(':id', 0, 'id', null), 'other'));
    }
}
