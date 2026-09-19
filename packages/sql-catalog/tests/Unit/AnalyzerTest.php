<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\UnknownExtensionException;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Source\SourceFile;
use SqlCatalog\Source\SourceScanException;

#[CoversClass(Analyzer::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(UnknownExtensionException::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceScanException::class)]
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
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Php\SyntaxException::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Source\SourceScanner::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
final class AnalyzerTest extends TestCase
{
    public function testExtensionsAreTheOnesTheRunCanAskFor(): void
    {
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo'], (new Analyzer())->extensions()->names());
        self::assertSame(['pdo'], (new Analyzer(new ExtensionRegistry([new PdoExtension()])))->extensions()->names());
    }

    public function testAnalyzePathsReadsTheCorpus(): void
    {
        $root = dirname(__DIR__, 2);
        $catalog = (new Analyzer())->analyzePaths([$root . '/corpus/app/Plain.php'], null, $root);
        self::assertGreaterThan(0, $catalog->count());
        self::assertSame('corpus/app/Plain.php', $catalog->entries()[0]->site->file);
    }

    public function testAnalyzePathsSkipsWhatIsExcluded(): void
    {
        $root = dirname(__DIR__, 2);
        $catalog = (new Analyzer())->analyzePaths([$root . '/corpus/app'], null, $root, ['corpus/*']);
        self::assertCount(0, $catalog);
    }

    public function testAnalyzePathsRefusesAPathItCannotRead(): void
    {
        $this->expectException(SourceScanException::class);
        (new Analyzer())->analyzePaths(['/definitely/not/here']);
    }

    public function testAnalyzeSourceCatalogsTheStatementsOfTheGivenSources(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d): void { $d->query("SELECT id FROM users"); }',
        ]);
        self::assertSame('SELECT id FROM users', $catalog->entries()[0]->sql());
    }

    public function testAnalyzeSourceResolvesAcrossFiles(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'schema.php' => '<?php namespace App; class Schema { public const TABLE = "users"; }',
            'repo.php' => '<?php namespace App; function f(\\PDO $d): void { $d->query("SELECT * FROM " . Schema::TABLE); }',
        ]);
        self::assertSame('SELECT * FROM users', $catalog->entries()[0]->sql());
    }

    public function testAnalyzeSourceReportsAFileItCannotParse(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['broken.php' => '<?php function {']);
        self::assertCount(0, $catalog);
        self::assertSame('broken.php', $catalog->problems()[0]->file);
    }

    public function testAnalyzeSourceRefusesAnExtensionThatIsNotRegistered(): void
    {
        $this->expectException(UnknownExtensionException::class);
        (new Analyzer())->analyzeSource(['a.php' => '<?php'], new AnalysisOptions(['symfony']));
    }

    public function testParseAnswersWithTheProblemThatStoppedIt(): void
    {
        $analyzer = new Analyzer();
        self::assertInstanceOf(ParsedFile::class, $analyzer->parse(new SourceFile('a.php', '<?php $a = 1;')));
        self::assertInstanceOf(AnalysisProblem::class, $analyzer->parse(new SourceFile('a.php', '<?php function {')));
    }

    public function testEntriesOfRecognisesOnlyTheExtensionsTheOptionsName(): void
    {
        $analyzer = new Analyzer();
        $catalog = $analyzer->analyzeSource(
            ['a.php' => '<?php function f(mysqli $m): void { $m->query("SELECT 1"); }'],
            new AnalysisOptions(['pdo']),
        );
        self::assertCount(0, $catalog);
    }

    public function testEntriesOfIsCalledWithTheParsedFiles(): void
    {
        $analyzer = new Analyzer();
        $file = (new \SqlCatalog\Php\SourceParser())->parse('a.php', '<?php function f(PDO $d) { $d->query("SELECT 1"); }');

        $entries = $analyzer->entriesOf([$file], new AnalysisOptions(['pdo']));

        self::assertCount(1, $entries);
        self::assertSame('SELECT 1', $entries[0]->sql());
    }

    public function testSortRecordsIsCalledWithTheEntries(): void
    {
        $analyzer = new Analyzer();
        $later = new CatalogEntry(
            'a',
            \SqlCatalog\Sql\StatementKind::Select,
            \SqlCatalog\Text\TextPattern::fromText('SELECT 1'),
            [],
            [],
            new \SqlCatalog\Catalog\CallSite('b.php', 1, 'f', 's'),
            [],
        );
        $earlier = new CatalogEntry(
            'b',
            \SqlCatalog\Sql\StatementKind::Select,
            \SqlCatalog\Text\TextPattern::fromText('SELECT 2'),
            [],
            [],
            new \SqlCatalog\Catalog\CallSite('a.php', 1, 'f', 's'),
            [],
        );

        self::assertSame([$earlier, $later], $analyzer->sortRecords([$later, $earlier]));
    }

    public function testSortRecordsOrdersByWhereTheStatementIsIssued(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'b.php' => '<?php function g(PDO $d): void { $d->query("SELECT 2"); }',
            'a.php' => '<?php function f(PDO $d): void { $d->query("SELECT 1"); }',
        ]);
        self::assertSame(['a.php', 'b.php'], array_map(
            static fn (CatalogEntry $entry): string => $entry->site->file,
            $catalog->entries(),
        ));
    }
}
