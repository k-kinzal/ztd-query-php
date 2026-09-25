<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Core\Catalog\AnalysisProblem;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\FindingRule;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Extension\ExtensionRegistry;
use SqlCatalog\Core\Extension\UnknownExtensionException;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Source\SourceFile;
use SqlCatalog\Core\Source\SourceScanException;
use SqlCatalog\Extension\Pdo\PdoExtension;
use SqlCatalog\Facade\AnalysisOptions;
use SqlCatalog\Facade\Analyzer;

#[CoversClass(Analyzer::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\StatementPart::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(UnknownExtensionException::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceScanException::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EntryFactory::class)]
#[UsesClass(EvaluationBudget::class)]
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
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Mysqli\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Core\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\SyntaxException::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Source\SourceScanner::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Core\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Core\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Core\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\MethodShape::class)]
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
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BranchArms::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Facade\Configuration::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\NamedModel::class)]
#[UsesClass(\SqlCatalog\Facade\ConfigurationSchema::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\Objects\ObjectEffects::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
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
#[CoversClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[CoversClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[CoversClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[CoversClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[CoversClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[CoversClass(\SqlCatalog\Core\Extension\Model\ModelSet::class)]
#[CoversClass(\SqlCatalog\Core\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\QueryOutput::class)]
final class AnalyzerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAnalyzeSourceBoundsDestructuredAlternativesBeforeTheyExhaustMemory(): void
    {
        $previousLimit = ini_set('memory_limit', '128M');
        self::assertIsString($previousLimit);
        try {
            $variables = array_map(static fn (int $index): string => '$v' . $index, range(0, 19));
            $source = '<?php function run(PDO $db, bool $flag) {'
                . '[' . implode(', ', $variables) . '] = ['
                . implode(', ', array_fill(0, 20, '$flag ? "1" : "2"')) . '];'
                . '$db->query("SELECT " . ' . implode(' . ", " . ', $variables) . '); }';

            $catalog = (new Analyzer())->analyzeSource(['query.php' => $source]);

            self::assertSame([], $catalog->problems());
            self::assertCount(12, $catalog->entries());
            self::assertContains('SELECT ' . implode(', ', array_fill(0, 20, '1')), array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries()));
            $joined = array_values(array_filter($catalog->entries(), static fn (CatalogEntry $entry): bool => !$entry->correlated));
            self::assertCount(1, $joined);
            self::assertFalse($joined[0]->isExact());
            self::assertFalse($joined[0]->searchClosed());
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

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
    public function testIssetSqlFragmentsKeepBothSyntacticBranches(string $source, array $expected): void
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
                ['SELECT * FROM users', 'SELECT * FROM users WHERE active = 1'],
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
                ['SELECT * FROM users', 'SELECT * FROM admins', 'SELECT * FROM admins WHERE admin = 1'],
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
        self::assertSame('<?php function f(PDO $d) { $d->query("SELECT 1"); }', $catalog->source('Repo.php'));
    }

    public function testAnalyzePathsSkipsWhatIsExcluded(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/Repo.php', '<?php function f(PDO $d) { $d->query("SELECT 1"); }');

        $catalog = (new Analyzer())->analyzePaths([$directory], null, $directory, ['Repo.php']);

        self::assertCount(0, $catalog);
        self::assertNull($catalog->source('Repo.php'));
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
        self::assertSame('<?php function {', $catalog->source('broken.php'));
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
        $file = (new \SqlCatalog\Core\Php\SourceParser())->parse('a.php', '<?php function f(PDO $d) { $d->query("SELECT 1"); }');

        $entries = $analyzer->entriesOf([$file], new AnalysisOptions(['pdo']));

        self::assertCount(1, $entries);
        self::assertSame('SELECT 1', $entries[0]->sql());
    }

    public function testSortRecordsIsCalledWithTheEntries(): void
    {
        $analyzer = new Analyzer();
        $later = new CatalogEntry(
            'a',
            \SqlCatalog\Core\Sql\StatementKind::Select,
            \SqlCatalog\Core\Text\TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('b.php', 1, 'f', 's'),
            [],
        );
        $earlier = new CatalogEntry(
            'b',
            \SqlCatalog\Core\Sql\StatementKind::Select,
            \SqlCatalog\Core\Text\TextPattern::fromText('SELECT 2'),
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
    public function testAnalyzeSourceNormalizesPlaceholderListsThroughVariables(): void
    {
        $source = <<<'PHP'
<?php
function findUsers(PDO $db, array $ids) {
    $size = count($ids);
    $marker = '?';
    $items = array_fill(0, $size, $marker);
    $marks = implode(',', $items);
    $db->prepare('SELECT * FROM users WHERE id IN (' . $marks . ')');
}
PHP;
        $analyzer = new Analyzer();
        self::assertSame('SELECT * FROM users WHERE id IN (?)', $analyzer->analyzeSource(['users.php' => $source])->entries()[0]->sql());
    }

    #[DataProvider('providerConfiguredPlaceholderLists')]
    public function testAnalyzeSourceHandlesPlaceholderExpressions(string $expression, string $expected): void
    {
        $source = '<?php function f(PDO $db, array $ids) { $db->prepare("SELECT * FROM users WHERE id IN (" . ' . $expression . ' . ")"); }';
        $catalog = (new Analyzer())->analyzeSource(['users.php' => $source]);
        self::assertCount(1, $catalog->entries());
        self::assertSame('SELECT * FROM users WHERE id IN (' . $expected . ')', $catalog->entries()[0]->sql());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerConfiguredPlaceholderLists(): array
    {
        return [
            'inline' => ["implode(',', array_fill(0, count(\$ids), '?'))", '?'],
            'join alias' => ["join(',', array_fill(0, count(\$ids), '?'))", '?'],
            'known count' => ["implode(',', array_fill(0, 10, '?'))", '?'],
            'zero count is deliberately normalized' => ["implode(',', array_fill(0, 0, '?'))", '?'],
            'different value is not normalized' => ["implode(',', array_fill(0, count(\$ids), 'x'))", '{$}'],
            'external value is not normalized' => ["implode(',', array_fill(0, count(\$ids), \$_GET['value']))", '{$}'],
        ];
    }

    public function testAnalyzeSourceAppliesRegisteredFunctionsAndFallsBackToSource(): void
    {
        $models = \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins();
        $models->register('App\table', static fn (array $arguments): ?\SqlCatalog\Core\Evaluation\Domain => ($arguments[0] ?? \SqlCatalog\Core\Evaluation\Domain::unknown())->soleLiteral()?->value === 'override' ? \SqlCatalog\Core\Evaluation\Domain::literal('modeled') : null);
        $source = <<<'PHP'
<?php
namespace App;
function table($which) { return 'original'; }
function run(\PDO $db) {
    $db->query('SELECT * FROM ' . table('override'));
    $db->query('SELECT * FROM ' . table('fallback'));
}
PHP;
        $entries = (new Analyzer(functionModels: $models))->analyzeSource(['app.php' => $source])->entries();
        self::assertSame(['SELECT * FROM modeled', 'SELECT * FROM original'], array_map(static fn ($entry): string => $entry->sql(), $entries));
    }

    public function testAnalyzeSourceDistinguishesNamespacedFunctionsAndGlobalBuiltins(): void
    {
        $source = <<<'PHP'
<?php
namespace App;
function strtoupper($value) { return 'local'; }
function run(\PDO $db) {
    $db->query('SELECT ' . strtoupper('foo'));
    $db->query('SELECT ' . \strtoupper('foo'));
}
PHP;
        $entries = (new Analyzer())->analyzeSource(['app.php' => $source])->entries();
        self::assertSame(['SELECT local', 'SELECT FOO'], array_map(static fn ($entry): string => $entry->sql(), $entries));
    }

    public function testWithConfigurationOverridesTheBuiltinWithoutChangingTheOriginalAnalyzer(): void
    {
        $analyzer = new Analyzer();
        $configured = $analyzer->withConfiguration(new \SqlCatalog\Facade\Configuration(functionModels: ['array_fill' => \Tests\Fake\PairModel::class]));
        $source = '<?php function f(PDO $db, array $ids) { $db->prepare("SELECT * FROM users WHERE id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")"); }';
        self::assertSame('SELECT * FROM users WHERE id IN (?,?)', $configured->analyzeSource(['users.php' => $source])->entries()[0]->sql());
        self::assertSame('SELECT * FROM users WHERE id IN (?)', $analyzer->analyzeSource(['users.php' => $source])->entries()[0]->sql());
    }

    #[DataProvider('providerUncertainWrites')]
    public function testUncertainWritesRemainOpenInsteadOfBecomingEmpty(string $body): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function mutate(&$x) { $x = "tail"; } function fragment() { ' . $body
                . ' return "SELECT * FROM users" . (isset($tail) ? $tail : ""); }'
                . ' function run(PDO $pdo) { $pdo->query(fragment()); }',
        ]);
        self::assertEqualsCanonicalizing(['SELECT * FROM users', 'SELECT * FROM users{$}'], array_map(
            static fn (CatalogEntry $entry): string => $entry->sql(),
            $catalog->entries(),
        ));
        self::assertSame([false, false], array_map(static fn (CatalogEntry $entry): bool => $entry->searchClosed(), $catalog->entries()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUncertainWrites(): array
    {
        return [
            'include' => ['include "fragment.php";'],
            'require' => ['require "fragment.php";'],
            'eval' => ['eval($code);'],
            'dynamic assignment' => ['$$name = "tail";'],
            'dynamic element assignment' => ['$$name[0] = "tail";'],
            'dynamic reference argument' => ['mutate($$name);'],
            'dynamic call' => ['$call($data);'],
            'builtin reference output' => ['str_replace("a", "b", "c", $tail);'],
            'extract' => ['extract($data);'],
            'known reference call' => ['mutate($tail);'],
            'named reference call' => ['mutate(x: $tail);'],
            'unknown reference call' => ['unknown($tail);'],
            'static reference call' => ['Unknown::mutate($tail);'],
            'method reference call' => ['$object->mutate($tail);'],
            'later alias write' => ['$alias =& $tail; $tail = "before"; $alias = "after";'],
            'escaped closure' => ['$callback = function () use (&$tail) { $tail = "after"; }; $tail = "before"; $callback();'],
            'global changed by call' => ['global $tail; $tail = "before"; unknown();'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerUnconditionalCandidates')]
    public function testUnconditionalCandidatesNormalizeOnlyWhenStringified(string $body, array $expected): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function fragment() { ' . $body . ' } function run(PDO $pdo) { $pdo->query("SELECT " . fragment()); }',
        ]);
        self::assertEqualsCanonicalizing($expected, array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries()));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function providerUnconditionalCandidates(): array
    {
        return [
            'true ternary' => ['return true ? "1" : "2";', ['SELECT 1', 'SELECT 2']],
            'false ternary' => ['return false ? "1" : "2";', ['SELECT 1', 'SELECT 2']],
            'no reaching assignment' => ['return isset($tail) ? $tail : "";', ['SELECT ']],
            'null and empty' => ['return true ? null : "";', ['SELECT ']],
            'literal empty and unresolved' => ['return true ? "" : unknown();', ['SELECT ', 'SELECT {$}']],
            'definite overwrite after include' => ['include "a.php"; $tail = "1"; return $tail;', ['SELECT 1']],
            'unset after include' => ['include "a.php"; unset($tail); return $tail;', ['SELECT ']],
            'ternary effects' => ['$tail = "0"; true ? ($tail = "1") : ($tail = "2"); return $tail;', ['SELECT 1', 'SELECT 2']],
            'optional ternary effect' => ['$tail = "0"; true ? ($tail = "1") : "2"; return $tail;', ['SELECT 0', 'SELECT 1']],
            'short circuit effect' => ['$tail = "0"; true && ($tail = "1"); return $tail;', ['SELECT 0', 'SELECT 1']],
            'match effects' => ['match (1) { 1 => $tail = "1", default => $tail = "2" }; return $tail;', ['SELECT 1', 'SELECT 2']],
        ];
    }

    public function testMixedExternalAndUnresolvedDependenciesDoNotCloseTheSearch(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $pdo) { $pdo->query("SELECT " . $_GET["x"] . unknown()); }',
        ]);
        self::assertSame(Resolution::IncompleteModel, $catalog->entries()[0]->resolution());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testFileScopeAliasesCannotHideLaterWrites(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php $alias =& $tail; $tail = "before"; $alias = "after"; $pdo = new PDO("sqlite::memory:"); $pdo->query("SELECT " . $tail);',
        ]);
        self::assertSame('SELECT {$}', $catalog->entries()[0]->sql());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }

    public function testAHelperReturnCutShortByTheLoopBudgetCannotCloseTheCaller(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function fragment() { $tail = ""; while (unknown()) { $tail .= "x"; } return $tail; } function run(PDO $pdo) { $pdo->query("SELECT " . fragment()); }',
        ], new AnalysisOptions(budget: new EvaluationBudget(maxLoopPasses: 1)));
        self::assertNotEmpty($catalog->entries());
        self::assertNotContains(true, array_map(static fn (CatalogEntry $entry): bool => $entry->searchClosed(), $catalog->entries()));
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

    public function testAnalyzeSourcePreservesShortCircuitAlternativesOnTrackedBuilders(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $q = DB::table("users"); $q->where("a", 1); false && $q->where("b", 2); $q->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        $sql = array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries());
        sort($sql);
        self::assertSame(['select * from "users" where "a" = ?', 'select * from "users" where "a" = ? and "b" = ?'], $sql);
        self::assertTrue($catalog->entries()[0]->searchClosed());
        self::assertTrue($catalog->entries()[1]->searchClosed());
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
        $extension = self::createStub(\SqlCatalog\Core\Extension\Model\ModelProviderInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('sinks')->willReturn([]);
        $extension->method('globals')->willReturn([]);
        $extension->method('models')->willReturn(new \SqlCatalog\Core\Extension\Model\ModelSet(calls: [static fn (\SqlCatalog\Core\Extension\Model\CallContext $call): ?\SqlCatalog\Core\Evaluation\Domain => $call->name === 'fragment' || $call->className === 'SqlText' ? \SqlCatalog\Core\Evaluation\Domain::literal('SELECT * FROM ')->concat($call->arguments[0]) : null]));
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
        $model = new class () implements \SqlCatalog\Core\Extension\Model\QueryModelInterface {
            public function inputs(\PhpParser\Node\Expr\CallLike $call): array
            {
                return array_map(static fn (\PhpParser\Node\Arg $argument): \PhpParser\Node\Expr => $argument->value, array_values($call->getArgs()));
            }

            public function statements(\PhpParser\Node\Expr\CallLike $call, array $values): array
            {
                return [new \SqlCatalog\Core\Extension\Model\QueryOutput(\SqlCatalog\Core\Evaluation\Domain::literal('SELECT * FROM ')->concat($values[0])->concat(\SqlCatalog\Core\Evaluation\Domain::literal(' WHERE id = ?')), \SqlCatalog\Core\Evaluation\Domain::of(new \SqlCatalog\Core\Evaluation\ArrayTerm([new \SqlCatalog\Core\Evaluation\ArrayEntry(null, $values[1])])))];
            }
        };
        $extension = self::createStub(\SqlCatalog\Core\Extension\Model\ModelProviderInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('sinks')->willReturn([new \SqlCatalog\Core\Extension\SinkSpec('example.run', \SqlCatalog\Core\Extension\SinkCallKind::FunctionCall, null, 'run', \SqlCatalog\Core\Extension\SinkRole::Modelled, model: 'example.query')]);
        $extension->method('globals')->willReturn([]);
        $extension->method('models')->willReturn(new \SqlCatalog\Core\Extension\Model\ModelSet(queries: ['example.query' => $model]));
        $catalog = (new Analyzer(new ExtensionRegistry([$extension])))->analyzeSource(['query.php' => '<?php function readRows(string $table, int $id) { run($table, $id); } readRows("users", 7); readRows("posts", 9);'], new AnalysisOptions(['example']));
        self::assertCount(2, $catalog->entries());
        self::assertSame(['SELECT * FROM posts WHERE id = ?', 'SELECT * FROM users WHERE id = ?'], array_map(static fn (CatalogEntry $entry): string => $entry->sql(), $catalog->entries()));
        self::assertSame([[9], [7]], array_map(static fn (CatalogEntry $entry): array => $entry->placeholders[0]->value->values ?? [], $catalog->entries()));
        self::assertTrue($catalog->entries()[0]->searchClosed());
        self::assertTrue($catalog->entries()[1]->searchClosed());
    }

    public function testAnalyzeSourceKeepsAModelledSinkWithoutARegisteredCompilerVisible(): void
    {
        $extension = self::createStub(\SqlCatalog\Core\Extension\ExtensionInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('globals')->willReturn([]);
        $extension->method('sinks')->willReturn([new \SqlCatalog\Core\Extension\SinkSpec('example.run', \SqlCatalog\Core\Extension\SinkCallKind::FunctionCall, null, 'run', \SqlCatalog\Core\Extension\SinkRole::Modelled, model: 'missing')]);
        $catalog = (new Analyzer(new ExtensionRegistry([$extension])))->analyzeSource(['query.php' => '<?php run();'], new AnalysisOptions(['example']));
        self::assertCount(1, $catalog->entries());
        self::assertFalse($catalog->entries()[0]->searchClosed());
    }    public function testAnalyzeSourceCombinesNamedFunctionModelsWithLaravelRegistrations(): void
    {
        $functions = \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins();
        $functions->register('table_name', static fn (array $arguments): \SqlCatalog\Core\Evaluation\Domain => \SqlCatalog\Core\Evaluation\Domain::literal('accounts'));
        $analyzer = new Analyzer(functionModels: $functions);
        $catalog = $analyzer->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; DB::table(table_name())->where("id", 7)->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "accounts" where "id" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }
    public function testAnalyzeSourceRetainsBindingsReusedByModelledBuilderCalls(): void
    {
        $catalog = (new Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; $id = 7; DB::table("users")->where("a", $id)->where("b", $id)->get();'], new AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertCount(1, $catalog->entries());
        self::assertSame('select * from "users" where "a" = ? and "b" = ?', $catalog->entries()[0]->sql());
        self::assertSame([7], $catalog->entries()[0]->placeholders[0]->value?->values);
        self::assertSame([7], $catalog->entries()[0]->placeholders[1]->value?->values);
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceLetsRegisteredCallModelsOwnTheirArgumentEffects(): void
    {
        $extension = self::createStub(\SqlCatalog\Core\Extension\Model\ModelProviderInterface::class);
        $extension->method('name')->willReturn('example');
        $extension->method('sinks')->willReturn([]);
        $extension->method('globals')->willReturn([]);
        $extension->method('models')->willReturn(new \SqlCatalog\Core\Extension\Model\ModelSet(calls: [static fn (\SqlCatalog\Core\Extension\Model\CallContext $call): ?\SqlCatalog\Core\Evaluation\Domain => $call->name === 'fragment' ? $call->arguments[0] : null]));
        $analyzer = new Analyzer(new ExtensionRegistry([new PdoExtension(), $extension]));
        $catalog = $analyzer->analyzeSource(['query.php' => '<?php $table = "items"; $pdo = new PDO("sqlite::memory:"); $pdo->query("SELECT * FROM " . fragment($table) . " JOIN " . fragment($table));'], new AnalysisOptions(['pdo', 'example']));
        self::assertSame('SELECT * FROM items JOIN items', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testAnalyzeSourceKeepsVariableNamesForUnresolvedSqlAndFragments(): void
    {
        $source = <<<'SOURCE'
<?php
function queries(PDO $pdo, string $input): void {
    $sql = buildSql();
    $pdo->prepare($sql);
    $pdo->prepare($input);
    $table = tableName();
    $pdo->query('SELECT id FROM ' . $table . ' WHERE active = 1');
    $pdo->prepare(buildSql());
}
SOURCE;
        $entries = (new Analyzer())->analyzeSource(['queries.php' => $source])->entries();

        self::assertCount(4, $entries);
        self::assertSame('$sql', $entries[0]->firstGap()?->variable);
        self::assertSame('\\buildSql()', $entries[0]->firstGap()->expression);
        self::assertSame('$input', $entries[1]->firstGap()?->variable);
        self::assertSame('$table', $entries[2]->firstGap()?->variable);
        self::assertNull($entries[3]->firstGap()?->variable);
        self::assertSame('{$}', $entries[0]->sql());
    }

    public function testAnalyzeSourceAcceptsAnApplicationSqlPolicy(): void
    {
        $policy = self::createStub(\SqlCatalog\Core\Sql\Dialect::class);
        $policy->method('identifierQuote')->willReturn('!');
        $dialects = new \SqlCatalog\Core\Sql\Dialects(['application' => $policy]);
        $extensions = new ExtensionRegistry([new \SqlCatalog\Extension\Laravel\LaravelExtension($dialects)]);
        $catalog = (new Analyzer($extensions))->analyzeSource([
            'app.php' => '<?php \Illuminate\Support\Facades\DB::table("items")->get();',
        ], new AnalysisOptions(['laravel'], dialect: 'application'));
        self::assertSame('select * from !items!', $catalog->entries()[0]->sql());
    }
}
