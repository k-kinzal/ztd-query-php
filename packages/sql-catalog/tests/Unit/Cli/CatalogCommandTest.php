<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Cli\ArtifactWriter;
use SqlCatalog\Cli\CatalogCommand;
use SqlCatalog\Cli\CommandLine;
use SqlCatalog\Cli\CommandLineParser;
use SqlCatalog\Cli\CommandResult;
use SqlCatalog\Cli\ExitCode;
use SqlCatalog\Cli\InvalidCommandLineException;
use SqlCatalog\Cli\UsageText;
use SqlCatalog\Filter\CatalogFilter;
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\JsonReporter;
use SqlCatalog\Reporter\ReporterRegistry;
use SqlCatalog\Reporter\TextReporter;

#[CoversClass(CatalogCommand::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(ArtifactWriter::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(CommandLineParser::class)]
#[UsesClass(CommandResult::class)]
#[UsesClass(UsageText::class)]
#[UsesClass(CatalogFilter::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(JsonReporter::class)]
#[UsesClass(ReporterRegistry::class)]
#[UsesClass(TextReporter::class)]
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
#[UsesClass(Severity::class)]
#[UsesClass(\SqlCatalog\Catalog\ValueDomain::class)]
#[UsesClass(InvalidCommandLineException::class)]
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
#[UsesClass(\SqlCatalog\Reporter\HtmlReporter::class)]
#[UsesClass(\SqlCatalog\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Source\SourceScanner::class)]
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
#[UsesClass(\SqlCatalog\Source\SourceScanException::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Catalog\Resolution::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Configuration::class)]
#[UsesClass(\SqlCatalog\InvalidConfigurationException::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\NamedModel::class)]
#[UsesClass(\SqlCatalog\ConfigurationSchema::class)]
final class CatalogCommandTest extends TestCase
{
    public function testRunAnswersTheHelp(): void
    {
        $result = (new CatalogCommand())->run(['--help']);
        self::assertSame(ExitCode::Success, $result->exitCode);
        self::assertStringContainsString('sql-catalog', $result->output);
    }

    public function testRunReportsAnInvalidCommandLine(): void
    {
        $result = (new CatalogCommand())->run(['--nope']);
        self::assertSame(ExitCode::InvalidCommandLine, $result->exitCode);
        self::assertStringContainsString('Unknown option', $result->error);
    }

    public function testRunReportsAPathItCannotRead(): void
    {
        $result = (new CatalogCommand())->run(['/definitely/not/here']);
        self::assertSame(ExitCode::SourceUnreadable, $result->exitCode);
        self::assertStringContainsString('Cannot read', $result->error);
    }

    public function testRunCatalogsTheNamedPathOntoStandardOutput(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/Repo.php', '<?php function f(PDO $d) { $d->exec("INSERT INTO users (id) VALUES (1)"); }');

        $result = (new CatalogCommand())->run(['--root', $directory, $directory]);

        self::assertSame(ExitCode::Success, $result->exitCode);
        self::assertStringContainsString('Repo.php', $result->output);
        self::assertStringContainsString('INSERT INTO users', $result->output);
        unlink($directory . '/Repo.php');
        rmdir($directory);
    }

    public function testExecuteRefusesToRunWithoutAPath(): void
    {
        $this->expectException(InvalidCommandLineException::class);
        $this->expectExceptionMessage('Name at least one file or directory');
        (new CatalogCommand())->execute(new CommandLine());
    }

    public function testExecuteAppliesTheFilter(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents(
            $directory . '/Repo.php',
            '<?php function f(PDO $d) { $d->query("SELECT 1"); $d->exec("DELETE FROM users"); }',
        );
        $command = new CommandLine(
            [$directory],
            null,
            'text',
            ['pdo'],
            new CatalogFilter(kinds: [\SqlCatalog\Sql\StatementKind::Delete]),
            [],
            $directory,
        );

        $result = (new CatalogCommand())->execute($command);

        self::assertStringContainsString('1 statement(s)', $result->output);
        unlink($directory . '/Repo.php');
        rmdir($directory);
    }

    public function testAnswerQueryListsTheExtensionsAndTheReporters(): void
    {
        $command = new CatalogCommand();
        self::assertStringContainsString('pdo', (string) $command->answerQuery(new CommandLine(listExtensions: true))?->output);
        self::assertStringContainsString('json', (string) $command->answerQuery(new CommandLine(listReporters: true))?->output);
        self::assertNull($command->answerQuery(new CommandLine(['src'])));
    }

    public function testReportWritesIntoTheDirectoryItIsGiven(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        $result = (new CatalogCommand())->report(new CommandLine(['src'], $directory, 'json'), new Catalog());
        self::assertStringContainsString('catalog.json', $result->output);
        self::assertFileExists($directory . '/catalog.json');
        unlink($directory . '/catalog.json');
        rmdir($directory);
    }

    public function testStatusReportsFindingsOnlyWhenAskedTo(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT " . $_GET["x"]); }',
        ]);
        $command = new CatalogCommand();
        self::assertSame(ExitCode::Success, $command->status(new CommandLine(['src']), $catalog));
        self::assertSame(
            ExitCode::FindingsReported,
            $command->status(new CommandLine(['src'], failOn: Severity::High), $catalog),
        );
        self::assertSame(
            ExitCode::Success,
            $command->status(new CommandLine(['src'], failOn: Severity::High), new Catalog()),
        );
    }
    public function testConfiguredAnalyzerOverridesModelsWithoutLeakingThem(): void
    {
        $command = new CatalogCommand();
        $configured = $command->configuredAnalyzer(new \SqlCatalog\Configuration(functionModels: ['array_fill' => \Tests\Fake\PairModel::class]));
        $source = '<?php function f(PDO $db, array $ids) { $db->prepare("SELECT * FROM users WHERE id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")"); }';
        self::assertSame('SELECT * FROM users WHERE id IN (?,?)', $configured->analyzeSource(['query.php' => $source])->entries()[0]->sql());
        self::assertSame('SELECT * FROM users WHERE id IN (?)', $command->configuredAnalyzer(new \SqlCatalog\Configuration())->analyzeSource(['query.php' => $source])->entries()[0]->sql());
    }

    public function testRunLoadsCatalogSettingsAndAllowsCliOverrides(): void
    {
        $directory = sys_get_temp_dir() . '/catalog-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/query.php', '<?php function f(PDO $db, array $ids) { $db->prepare("SELECT * FROM users WHERE id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")"); }');
        file_put_contents($directory . '/.catalog.yaml', "paths: [query.php]\nextensions: [pdo]\nreporter: json\nfunction-models:\n  array_fill: Tests\\Fake\\PairModel\n");
        try {
            $command = new CatalogCommand();
            $result = $command->run(['--config', $directory . '/.catalog.yaml', '--reporter=text']);
            self::assertSame(ExitCode::Success, $result->exitCode);
            self::assertStringContainsString('SELECT * FROM users WHERE id IN (?,?)', $result->output);
            self::assertStringContainsString('query.php:', $result->output);
            $withoutConfig = $command->run([$directory . '/query.php']);
            self::assertStringContainsString('SELECT * FROM users WHERE id IN (?)', $withoutConfig->output);
        } finally {
            unlink($directory . '/query.php');
            unlink($directory . '/.catalog.yaml');
            rmdir($directory);
        }
    }

    public function testRunReportsMissingConfigurationAsAnInvalidCommandLine(): void
    {
        $result = (new CatalogCommand())->run(['--config=/definitely/missing/.catalog.yaml', 'src']);
        self::assertSame(ExitCode::InvalidCommandLine, $result->exitCode);
        self::assertStringContainsString('Cannot read configuration', $result->error);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidConfiguration')]
    public function testRunReportsConfigurationErrors(string $source, string $message): void
    {
        $path = sys_get_temp_dir() . '/sql-catalog-config-' . bin2hex(random_bytes(6)) . '.yaml';
        file_put_contents($path, $source);
        try {
            $result = (new CatalogCommand())->run(['--config', $path, 'src']);
            self::assertSame(ExitCode::InvalidCommandLine, $result->exitCode);
            self::assertStringContainsString($path, $result->error);
            self::assertStringContainsString($message, $result->error);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerInvalidConfiguration(): array
    {
        return [
            'not a mapping' => ['[]', 'must contain a YAML mapping'],
            'syntax error' => ['paths: [', 'Invalid configuration'],
            'unknown model' => ["function-models:\n  array_fill: Missing\\Model\n", 'must be an autoloadable callable'],
        ];
    }

    public function testRunDoesNotLoadConfigurationWhenPrintingHelp(): void
    {
        $result = (new CatalogCommand())->run(['--config=/definitely/missing/.catalog.yaml', '--help']);
        self::assertSame(ExitCode::Success, $result->exitCode);
        self::assertStringContainsString('--config=FILE', $result->output);
    }

}
