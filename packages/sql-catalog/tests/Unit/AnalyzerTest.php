<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Resolution;
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
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(UnknownExtensionException::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceScanException::class)]
#[UsesClass(\SqlCatalog\Analysis\BodyWalker::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\EntryFactory::class)]
#[UsesClass(EvaluationBudget::class)]
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
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Catalog\FindingRule::class)]
#[UsesClass(\SqlCatalog\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Evaluation\PathSet::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
final class AnalyzerTest extends TestCase
{
    public function testACallSiteSurvivesFailingToResolveItsStatement(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, string $sql): void { $d->query($sql); }',
        ]);

        self::assertCount(1, $catalog);
        self::assertSame('pdo.query', $catalog->entries()[0]->site->sink);
        self::assertFalse($catalog->entries()[0]->isExact());
    }

    public function testACallSiteSurvivesTheWalkNotReachingIt(): void
    {
        $options = new AnalysisOptions(['pdo'], new EvaluationBudget(2));
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d): void { $a = 1; $b = 2; $c = 3; $d->query("SELECT 1"); }',
        ], $options);

        self::assertCount(1, $catalog);
        self::assertSame(Resolution::Incomplete, $catalog->entries()[0]->resolution());
        self::assertFalse($catalog->entries()[0]->resolution()->isClosed());
    }

    public function testACallSiteSurvivesSittingInAnOperandNothingNeedsTheValueOf(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, bool $on): void { $on && $d->query("SELECT 1"); }',
        ]);

        self::assertSame(['SELECT 1'], array_map(
            static fn (CatalogEntry $entry): string => $entry->sql(),
            $catalog->entries(),
        ));
    }

    public function testValuesDecidedByOneBranchStayTogether(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, bool $admin): void {'
                . ' if ($admin) { $t = "admins"; $c = "admin_id"; } else { $t = "users"; $c = "user_id"; }'
                . ' $d->query("SELECT $c FROM $t"); }',
        ]);

        $found = array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries());
        sort($found);

        self::assertSame(['SELECT admin_id FROM admins', 'SELECT user_id FROM users'], $found);
    }

    public function testValuesDecidedByNestedBranchesStayTogether(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, bool $a, bool $b): void {'
                . ' if ($a) { $t = "x"; } else { $t = "y"; }'
                . ' if ($b) { $o = "ASC"; } else { $o = "DESC"; }'
                . ' $d->query("SELECT * FROM $t ORDER BY id $o"); }',
        ]);

        $found = array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries());
        sort($found);

        self::assertSame([
            'SELECT * FROM x ORDER BY id ASC',
            'SELECT * FROM x ORDER BY id DESC',
            'SELECT * FROM y ORDER BY id ASC',
            'SELECT * FROM y ORDER BY id DESC',
        ], $found);
    }

    public function testAResolvedCallerDoesNotDeleteAnUndeterminedOne(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function run(PDO $d, string $sql): void { $d->query($sql); }'
                . ' function a(PDO $d): void { run($d, "SELECT 1"); }'
                . ' function b(PDO $d, string $outside): void { run($d, $outside); }',
        ]);

        $found = array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries());
        sort($found);

        self::assertSame(['SELECT 1', '{$}'], $found);
    }

    public function testAnUndeterminedStatementSaysWhyItIsUndetermined(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'external.php' => '<?php function f(PDO $d): void { $d->query("SELECT " . $_GET["x"]); }',
            'model.php' => '<?php function g(PDO $d, string $s): void { $d->query("SELECT " . $s); }',
        ]);

        $reasons = array_map(
            static fn (CatalogEntry $entry): string => $entry->site->file . '=' . $entry->resolution()->value,
            $catalog->entries(),
        );

        self::assertSame(['external.php=external-input', 'model.php=incomplete-model'], $reasons);
    }

    public function testAlternativesPairedAcrossIndependentPartsAreMarkedAsSuch(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function pick(bool $a): string { return $a ? "x" : "y"; }'
                . ' function f(PDO $d, bool $p, bool $q): void { $d->query("SELECT " . pick($p) . " FROM " . pick($q)); }',
        ]);

        self::assertNotSame([], $catalog->entries());
        self::assertFalse($catalog->entries()[0]->correlated);
    }

    public function testExtensionsAreTheOnesTheRunCanAskFor(): void
    {
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo', 'wordpress'], (new Analyzer())->extensions()->names());
        self::assertSame(['pdo'], (new Analyzer(new ExtensionRegistry([new PdoExtension()])))->extensions()->names());
    }

    public function testAnalyzePathsReadsTheFilesUnderThem(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/Repo.php', '<?php function f(PDO $d) { $d->query("SELECT 1"); }');

        $catalog = (new Analyzer())->analyzePaths([$directory], null, $directory);

        self::assertSame('Repo.php', $catalog->entries()[0]->site->file);
        unlink($directory . '/Repo.php');
        rmdir($directory);
    }

    public function testAnalyzePathsSkipsWhatIsExcluded(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/Repo.php', '<?php function f(PDO $d) { $d->query("SELECT 1"); }');

        $catalog = (new Analyzer())->analyzePaths([$directory], null, $directory, ['Repo.php']);

        self::assertCount(0, $catalog);
        unlink($directory . '/Repo.php');
        rmdir($directory);
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
