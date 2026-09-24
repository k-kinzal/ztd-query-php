<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\FindingRule;
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
#[UsesClass(\SqlCatalog\Analysis\EntryFactory::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
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
#[UsesClass(\SqlCatalog\Catalog\Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(\SqlCatalog\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BranchArms::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[CoversClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\Objects\CallbackEffects::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\Objects\ObjectEffects::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[CoversClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[CoversClass(\SqlCatalog\Analysis\Interpreter::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\BuilderCalls::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\BuilderQueries::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\Clauses::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\Grammar::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\ModelMetadata::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\Predicates::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\QueryState::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\SelectCompiler::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\WriteCompiler::class)]
#[CoversClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[CoversClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[CoversClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[CoversClass(\SqlCatalog\Evaluation\Environment::class)]
#[CoversClass(\SqlCatalog\Evaluation\ObjectMemory::class)]
#[CoversClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[CoversClass(\SqlCatalog\Analysis\Derivation\Objects\BranchEffects::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[CoversClass(\SqlCatalog\Extension\Model\ModelSet::class)]
#[CoversClass(\SqlCatalog\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\QueryOutput::class)]
final class AnalyzerTest extends TestCase
{
    public function testIssetGuardsAConditionallyAssignedSqlFragment(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => <<<'PHP'
                <?php
                function findUsers(PDO $pdo, bool $active): void {
                    if ($active) {
                        $where = ' WHERE active = 1';
                    }
                    $pdo->prepare('SELECT * FROM users' . (isset($where) ? $where : '') . '');
                }
                PHP,
        ]);

        self::assertEqualsCanonicalizing(
            ['SELECT * FROM users WHERE active = 1', 'SELECT * FROM users'],
            array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries()),
        );
        self::assertNotContains(false, array_map(
            static fn (CatalogEntry $entry): bool => $entry->resolution() === Resolution::Resolved && $entry->searchClosed(),
            $catalog->entries(),
        ));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerIssetSqlFragments')]
    public function testIssetSqlFragmentsKeepTheReachableStatements(string $source, array $expected): void
    {
        $catalog = (new Analyzer())->analyzeSource(['a.php' => '<?php ' . $source]);

        self::assertEqualsCanonicalizing($expected, array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries()));
        self::assertNotContains(false, array_map(
            static fn (CatalogEntry $entry): bool => $entry->resolution() === Resolution::Resolved && $entry->searchClosed(),
            $catalog->entries(),
        ));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function providerIssetSqlFragments(): array
    {
        return [
            'method' => [
                'class Q { public function run(PDO $pdo, bool $on): void { if ($on) { $tail = " WHERE active = 1"; }'
                    . ' $pdo->prepare("SELECT * FROM users" . (isset($tail) ? $tail : "")); } }',
                ['SELECT * FROM users', 'SELECT * FROM users WHERE active = 1'],
            ],
            'helper return' => [
                'function tail(bool $on): string { if ($on) { $tail = " WHERE active = 1"; } return isset($tail) ? $tail : ""; }'
                    . ' function run(PDO $pdo, bool $on): void { $pdo->prepare("SELECT * FROM users" . tail($on)); }',
                ['SELECT * FROM users', 'SELECT * FROM users WHERE active = 1'],
            ],
            'null initialization' => [
                'function run(PDO $pdo, bool $on): void { $tail = null; if ($on) { $tail = " WHERE active = 1"; }'
                    . ' $pdo->prepare("SELECT * FROM users" . (isset($tail) ? $tail : "")); }',
                ['SELECT * FROM users', 'SELECT * FROM users WHERE active = 1'],
            ],
            'unset' => [
                'function run(PDO $pdo): void { $tail = " WHERE active = 1"; unset($tail);'
                    . ' $pdo->prepare("SELECT * FROM users" . (isset($tail) ? $tail : "")); }',
                ['SELECT * FROM users'],
            ],
            'empty and false values are set' => [
                'function run(PDO $pdo, bool $on): void { $tail = $on ? "" : false;'
                    . ' $pdo->prepare("SELECT * FROM users" . (isset($tail) ? $tail : " WHERE active = 1")); }',
                ['SELECT * FROM users'],
            ],
            'two optional fragments' => [
                'function run(PDO $pdo, bool $filter, bool $sort): void { if ($filter) { $where = " WHERE active = 1"; }'
                    . ' if ($sort) { $order = " ORDER BY id"; }'
                    . ' $pdo->prepare("SELECT * FROM users" . (isset($where) ? $where : "") . (isset($order) ? $order : "")); }',
                ['SELECT * FROM users', 'SELECT * FROM users WHERE active = 1', 'SELECT * FROM users ORDER BY id', 'SELECT * FROM users WHERE active = 1 ORDER BY id'],
            ],
            'correlated variables' => [
                'function run(PDO $pdo, bool $on): void { if ($on) { $table = "admins"; $tail = " WHERE admin = 1"; } else { $table = "users"; }'
                    . ' $pdo->prepare("SELECT * FROM " . $table . (isset($tail) ? $tail : "")); }',
                ['SELECT * FROM users', 'SELECT * FROM admins WHERE admin = 1'],
            ],
            'nullable parameter supplied by callers' => [
                'function run(PDO $pdo, ?string $tail): void { $pdo->prepare("SELECT * FROM users" . (isset($tail) ? $tail : "")); }'
                    . ' function callers(PDO $pdo): void { run($pdo, null); run($pdo, " WHERE active = 1"); }',
                ['SELECT * FROM users', 'SELECT * FROM users WHERE active = 1'],
            ],
        ];
    }

    #[DataProvider('providerUnknownIssetSqlFragment')]
    public function testIssetDoesNotDiscardAnUnknownSqlFragment(string $body): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function run(PDO $pdo, $input): void { ' . $body
                . ' $pdo->prepare("SELECT * FROM users" . (isset($tail) ? $tail : "")); }',
        ]);

        self::assertEqualsCanonicalizing(['SELECT * FROM users', 'SELECT * FROM users{$}'], array_map(
            static fn (CatalogEntry $entry): string => $entry->sql(),
            $catalog->entries(),
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnknownIssetSqlFragment(): array
    {
        return [
            'external input' => ['$tail = $_GET["tail"];'],
            'unknown call' => ['$tail = unknown();'],
            'parameter' => ['$tail = $input;'],
            'declared global' => ['global $tail;'],
            'unsupported expression' => ['$tail = $input + 1;'],
        ];
    }

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
        self::assertSame(Resolution::NotAnalyzed, $catalog->entries()[0]->resolution());
        self::assertFalse($catalog->entries()[0]->resolution()->isClosed());
        self::assertTrue($catalog->entries()[0]->hasFinding(FindingRule::CallNotAnalyzed));
    }

    public function testACallOnAClassTheExtensionsDoNotNameIsNotReportedAtAll(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php class Q { public function query(string $s): void {} }'
                . ' function f(Q $q): void { $q->query("SELECT 1"); }',
        ], new AnalysisOptions(['pdo']));

        self::assertCount(0, $catalog);
    }

    public function testACallOnSomethingThatCouldNotBeNamedIsReportedAsUnmatched(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f($q): void { $q->query("SELECT 1"); }',
        ], new AnalysisOptions(['pdo']));

        self::assertCount(1, $catalog);
        self::assertSame(CallSite::UNMATCHED, $catalog->entries()[0]->site->sink);
        self::assertSame(Resolution::NotAnalyzed, $catalog->entries()[0]->resolution());
    }

    public function testAHandleReachedThroughAGlobalIsRecognisedWhenSomethingSaysWhatItIs(): void
    {
        $analyzer = new Analyzer();

        $undocumented = $analyzer->analyzeSource([
            'a.php' => '<?php function f(): void { global $db; $db->query("SELECT 1"); }',
        ], new AnalysisOptions(['pdo']));

        self::assertSame(CallSite::UNMATCHED, $undocumented->entries()[0]->site->sink);

        $documented = $analyzer->analyzeSource([
            'a.php' => '<?php' . "\n" . '/** @global PDO $db */' . "\n"
                . 'function f(): void { global $db; $db->query("SELECT 1"); }',
        ], new AnalysisOptions(['pdo']));

        self::assertSame(['SELECT 1'], array_map(
            static fn (CatalogEntry $entry): string => $entry->sql(),
            $documented->entries(),
        ));
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

    public function testAValueBoundToOnePlaceholderStaysWithThatPlaceholder(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            't.php' => '<?php function f(PDO $d): void { $s = $d->prepare("SELECT * FROM t WHERE a = ? AND b = ?"); $s->bindValue(2, "x"); $s->execute(); }',
        ]);

        self::assertNull($catalog->entries()[0]->placeholders[0]->value);
        self::assertSame(['x'], $catalog->entries()[0]->placeholders[1]->value?->values);
        self::assertTrue($catalog->entries()[0]->hasFinding(FindingRule::PlaceholderCountMismatch));
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
            new CallSite('b.php', 1, 'f', 's'),
            [],
        );
        $earlier = new CatalogEntry(
            'b',
            \SqlCatalog\Sql\StatementKind::Select,
            \SqlCatalog\Text\TextPattern::fromText('SELECT 2'),
            [],
            [],
            new CallSite('a.php', 1, 'f', 's'),
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

    public function testAnalyzeSourceKeepsDirectBuilderArrayWritesOpen(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function f() { $q = DB::table("users"); $q->wheres[] = []; $q->get(); }'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));

        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceKeepsReferenceRebindingOfBuildersOpen(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function f() { $q = DB::table("users"); $alias =& $q; $alias->where("id", 1); $q->get(); }'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));

        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceFollowsBuilderAliasesStoredInArrays(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function f() { $q = DB::table("users"); $aliases = [$q]; $aliases[0]->where("id", 1); $q->get(); }'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));

        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users" where "id" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceKeepsConditionalMutationsAsAlternatives(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function f(bool $active) { $q = DB::table("users"); $active ? $q->where("a", 1) : $q->where("b", 2); $q->get(); }'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        $sql = array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries());
        sort($sql);
        self::assertSame(['select * from "users" where "a" = ?', 'select * from "users" where "b" = ?'], $sql);
    }

    public function testAnalyzeSourceRetainsTheLimitAfterReusingAFirstQuery(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $q = DB::table("users"); $q->first(); $q->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(2, $catalog->entries());
        self::assertSame('select * from "users" limit 1', $catalog->entries()[1]->sql());
    }

    public function testAnalyzeSourceDoesNotMistakeCollectionMethodsForDatabaseExecutions(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; DB::table("users")->get()->first();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users"', $catalog->entries()[0]->sql());
    }

    public function testAnalyzeSourceRecognizesAuthenticationModelsWithoutVendorFiles(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php class User extends \\Illuminate\\Foundation\\Auth\\User {} User::where("id", 1)->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users" where "id" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceDoesNotLoseAlternativesWhenMutatingAJoinedBuilder(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function f(bool $active) { $q = DB::table("users"); $active ? $q->where("a", 1) : $q->where("b", 2); $q->limit(3); $q->get(); }'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        $sql = array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries());
        sort($sql);
        self::assertSame(['select * from "users" where "a" = ? limit 3', 'select * from "users" where "b" = ? limit 3'], $sql);
    }

    public function testAnalyzeSourceKeepsObjectsPassedInsideArraysToUnknownHelpersOpen(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $q = DB::table("users"); $box = [$q]; unknown($box); $q->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceDoesNotAssumeCustomModelOrderingUsesTheStandardBuilder(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { public function orderBy($column) { return $this->newQuery()->where("tenant", 7); } } User::orderBy("id")->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }
    public function testAnalyzeSourceHonorsShortCircuitConditionsOnTrackedBuilders(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $q = DB::table("users"); $q->where("a", 1) && $q->where("b", 2); false && $q->where("c", 3); $q ?? $q->where("d", 4); $q->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users" where "a" = ? and "b" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }


    public function testAnalyzeSourceKeepsCapturedScalarBindingsInNestedPredicates(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $id = 7; DB::table("users")->where("active", 1)->where(function ($q) use ($id) { $q->where("id", $id)->orWhereNull("email"); })->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users" where "active" = ? and ("id" = ? or "email" is null)', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourcePassesArgumentsToTraditionalLocalScopes(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { public function scopeForTenant($q, int $tenant) { return $q->where("tenant_id", $tenant); } } User::forTenant(7)->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users" where "tenant_id" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceDoesNotLoseUnknownEffectsInsideNestedPredicates(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; DB::table("users")->where(fn ($q) => $q->customFilter())->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceKeepsEarlyReturningScopesAndBooleanRegroupingOpen(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { public function scopeEarly($q) { return $q; $q->where("id", 1); } public function scopeEither($q) { return $q->where("a", 1)->orWhere("b", 2); } } User::early()->get(); User::either()->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(2, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
        self::assertFalse($catalog->entries()[1]->searchClosed());
    }

    public function testAnalyzeSourceKeepsCapturedBuilderEffectsOpen(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $other = DB::table("posts"); DB::table("users")->where(function ($q) use ($other) { $other->where("id", 7); $q->where("id", 1); })->get(); $other->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(2, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
        self::assertFalse($catalog->entries()[1]->searchClosed());
    }
    public function testAnalyzeSourceDoesNotLoseArgumentSideEffectsOnTheBuilderReceiver(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function customize($q) { $q->where("tenant_id", 7); return 1; } $q = DB::table("users"); $q->where("active", customize($q))->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceRefreshesTerminalReceiversAfterEvaluatingArguments(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function columns($q) { $q->where("tenant_id", 7); return ["id"]; } $q = DB::table("users"); $q->get(columns($q));'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceRecognizesBothRawAndBuilderCallsOnConcreteConnections(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php function run(\\Illuminate\\Database\\MySqlConnection $db) { $db->select("SELECT 1"); $db->table("users")->get(); }'], new AnalysisOptions(['laravel']));
        self::assertCount(2, $catalog->entries());
        self::assertSame('SELECT 1', $catalog->entries()[0]->sql());
        self::assertSame('select * from `users`', $catalog->entries()[1]->sql());
    }

    #[DataProvider('providerUnmodelledBuilderEscapes')]
    public function testAnalyzeSourceKeepsEachUnmodelledEscapeOfABuilderOpen(string $call): void
    {
        $source = '<?php use Illuminate\\Support\\Facades\\DB; function f(callable $fn, object $other, string $class, string $method) { $q = DB::table("users"); '.$call.'; $q->get(); }';
        $catalog = (new Analyzer())->analyzeSource(['query.php' => $source], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerUnmodelledBuilderEscapes(): iterable
    {
        yield ['unknown($q)'];
        yield ['$fn($q)'];
        yield ['$other->customize($q)'];
        yield ['$other->$method($q)'];
        yield ['Unknown::customize($q)'];
        yield ['$class::$method($q)'];
        yield ['new Unknown($q)'];
        yield ['unknown([$q])'];
        yield ['unknown(fn () => $q->where("id", 7))'];
        yield ['$q->$method()'];
    }

    #[DataProvider('providerNamedBuilderArguments')]
    public function testAnalyzeSourceKeepsNamedBuilderArgumentsOpen(string $call): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; '.$call.';'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerNamedBuilderArguments(): iterable
    {
        yield ['DB::table(table: "users")->get()'];
        yield ['DB::table("users")->where(column: "id", value: 7)->get()'];
        yield ['DB::table("users")->get(columns: ["id"])'];
    }

    #[DataProvider('providerCustomSqlCalls')]
    public function testAnalyzeSourceAllowsExtensionsToModelSqlFragmentsFromAnyCallForm(string $expression): void
    {
        $extension = self::createStub(\SqlCatalog\Extension\Model\ModelProviderInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('sinks')->willReturn([]);
        $extension->method('globals')->willReturn([]);
        $extension->method('models')->willReturn(new \SqlCatalog\Extension\Model\ModelSet(calls: [static fn (\SqlCatalog\Extension\Model\CallContext $call): ?\SqlCatalog\Evaluation\Domain => $call->name === 'fragment' || $call->className === 'SqlText' ? \SqlCatalog\Evaluation\Domain::literal('SELECT * FROM ')->concat($call->arguments[0]) : null]));
        $analyzer = new Analyzer(new ExtensionRegistry([new PdoExtension(), $extension]));
        $source = ['query.php' => '<?php $table = "items"; $pdo = new PDO("sqlite::memory:"); $pdo->query('.$expression.');'];
        $catalog = $analyzer->analyzeSource($source, new AnalysisOptions(['pdo', 'example']));
        self::assertSame('SELECT * FROM items', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
        self::assertFalse($analyzer->analyzeSource($source, new AnalysisOptions(['pdo']))->entries()[0]->searchClosed());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerCustomSqlCalls(): iterable
    {
        yield ['fragment($table)'];
        yield ['Demo::fragment($table)'];
        yield ['(new Demo())->fragment($table)'];
        yield ['new SqlText($table)'];
    }

    public function testAnalyzeSourceSupportsRegisteredQueryModelsWithoutLaravel(): void
    {
        $model = new class () implements \SqlCatalog\Extension\Model\QueryModelInterface {
            public function inputs(\PhpParser\Node\Expr\CallLike $call): array
            {
                return array_map(static fn (\PhpParser\Node\Arg $argument): \PhpParser\Node\Expr => $argument->value, array_values($call->getArgs()));
            }

            public function statements(\PhpParser\Node\Expr\CallLike $call, array $values): array
            {
                return [new \SqlCatalog\Extension\Model\QueryOutput(\SqlCatalog\Evaluation\Domain::literal('SELECT * FROM ')->concat($values[0])->concat(\SqlCatalog\Evaluation\Domain::literal(' WHERE id = ?')), \SqlCatalog\Evaluation\Domain::of(new \SqlCatalog\Evaluation\ArrayTerm([new \SqlCatalog\Evaluation\ArrayEntry(null, $values[1])])))];
            }
        };
        $extension = self::createStub(\SqlCatalog\Extension\Model\ModelProviderInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('sinks')->willReturn([new \SqlCatalog\Extension\SinkSpec('example.run', \SqlCatalog\Extension\SinkCallKind::FunctionCall, null, 'run', \SqlCatalog\Extension\SinkRole::Modelled, model: 'example.query')]);
        $extension->method('globals')->willReturn([]);
        $extension->method('models')->willReturn(new \SqlCatalog\Extension\Model\ModelSet(queries: ['example.query' => $model]));
        $catalog = (new Analyzer(new ExtensionRegistry([$extension])))->analyzeSource(['query.php' => '<?php function readRows(string $table, int $id) { run($table, $id); } readRows("users", 7); readRows("posts", 9);'], new AnalysisOptions(['example']));
        self::assertCount(2, $catalog->entries());
        self::assertSame(['SELECT * FROM posts WHERE id = ?', 'SELECT * FROM users WHERE id = ?'], array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries()));
        self::assertSame([[9], [7]], array_map(static fn (CatalogEntry $entry): array => $entry->placeholders[0]->value->values ?? [], $catalog->entries()));
        self::assertTrue($catalog->entries()[0]->searchClosed());
        self::assertTrue($catalog->entries()[1]->searchClosed());
    }

    public function testAnalyzeSourceKeepsAModelledSinkWithoutARegisteredCompilerVisible(): void
    {
        $extension = self::createStub(\SqlCatalog\Extension\ExtensionInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('globals')->willReturn([]);
        $extension->method('sinks')->willReturn([new \SqlCatalog\Extension\SinkSpec('example.run', \SqlCatalog\Extension\SinkCallKind::FunctionCall, null, 'run', \SqlCatalog\Extension\SinkRole::Modelled, model: 'missing')]);
        $catalog = (new Analyzer(new ExtensionRegistry([$extension])))->analyzeSource(['query.php' => '<?php run();'], new AnalysisOptions(['example']));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

}
