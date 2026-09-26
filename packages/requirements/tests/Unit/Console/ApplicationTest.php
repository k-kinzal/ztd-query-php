<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use JsonException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\CoverageThresholds;
use Requirements\Config\DefinitionReader;
use Requirements\Config\Definitions;
use Requirements\Config\DocumentReader;
use Requirements\Config\ExtensionClasses;
use Requirements\Config\JsonSchemaFile;
use Requirements\Config\LinkValidator;
use Requirements\Config\Loader;
use Requirements\Config\MarkdownDocument;
use Requirements\Config\SchemaValidator;
use Requirements\Console\Application;
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\CoverageTable;
use Requirements\Console\Executor;
use Requirements\Console\ItemRecord;
use Requirements\Console\Options;
use Requirements\Console\Overview;
use Requirements\Console\Reporter;
use Requirements\Console\SpecificationReport;
use Requirements\Console\SpecificationTable;
use Requirements\Console\Text;
use Requirements\Console\Verdict;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\LiteralMask;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Validator;
use Requirements\Ears\Wording;
use Requirements\Input\Fields;
use Requirements\Model\Excerpt;
use Requirements\Model\Item;
use Requirements\Model\ItemValidator;
use Requirements\Model\Project;
use Requirements\Model\Source;
use Requirements\Report\Analysis;
use Requirements\Report\Analyzer;
use Requirements\Report\Claims;
use Requirements\Report\Coverage;
use Requirements\Report\EvidenceMatcher;
use Requirements\Report\SourceUnit;
use Requirements\Report\UnitCollector;
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\LocalFile;
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextFragment;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use Requirements\Test\Registry as TestRegistry;
use Requirements\Verification\TargetResults;
use Requirements\Verification\TestExecution;
use Requirements\Verification\VerificationResult;
use Requirements\Verification\Verifier;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Application::class)]
#[UsesClass(CoverageThresholds::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(ExtensionClasses::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(LinkValidator::class)]
#[UsesClass(Loader::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(Executor::class)]
#[UsesClass(SpecificationReport::class)]
#[UsesClass(Options::class)]
#[UsesClass(Overview::class)]
#[UsesClass(Reporter::class)]
#[UsesClass(Text::class)]
#[UsesClass(Verdict::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Wording::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(Item::class)]
#[UsesClass(ItemValidator::class)]
#[UsesClass(Project::class)]
#[UsesClass(Source::class)]
#[UsesClass(Analysis::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Claims::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(UnitCollector::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(SourceRegistry::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(Unit::class)]
#[UsesClass(TestRegistry::class)]
#[UsesClass(CoverageTable::class)]
#[UsesClass(Coverage::class)]
#[UsesClass(ItemRecord::class)]
#[UsesClass(SpecificationTable::class)]
#[UsesClass(VerificationResult::class)]
#[UsesClass(Verifier::class)]
#[UsesClass(TestExecution::class)]
#[UsesClass(TargetResults::class)]
#[Small]
final class ApplicationTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        putenv('SHELL_VERBOSITY');
        unset($_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']);
    }

    /**
     * @param list<string> $arguments
     * @throws JsonException
     */
    #[DataProvider('providerRunWithoutAProject')]
    public function testRunReturnsTheExitCodeWithoutAProject(array $arguments, int $expected): void
    {
        self::assertSame($expected, (new Application())->run($arguments));
    }

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function providerRunWithoutAProject(): array
    {
        return [
            'overview' => [['--quiet'], 0],
            'overview with short quiet' => [['-q'], 0],
            'version' => [['--version', '--quiet'], 0],
            'unknown command' => [['unknown', '--quiet'], 2],
            'unknown command as JSON' => [['--json', 'unknown', '--quiet'], 2],
            'unknown option' => [['lint', '--unknown', '--quiet'], 2],
            'missing configuration' => [['lint', '--config', '/nonexistent/requirements.yaml', '--quiet'], 2],
            'missing configuration as JSON' => [['lint', '--config', '/nonexistent/requirements.yaml', '--json', '--quiet'], 2],
        ];
    }

    /**
     * @param list<string> $arguments
     * @throws JsonException
     */
    #[DataProvider('providerRunInAProject')]
    public function testRunReturnsTheExitCodeOfTheCommand(array $arguments, int $expected): void
    {
        $project = new ProjectDirectory();
        self::assertSame($expected, (new Application())->run([...$arguments, '--config', $project->path('requirements.yaml'), '--quiet']));
    }

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function providerRunInAProject(): array
    {
        return [
            'lint' => [['lint'], 0],
            'lint as JSON' => [['lint', '--json'], 0],
            'check' => [['check'], 0],
            'failed coverage gate' => [['coverage', '--min-coverage=100'], 1],
            'invalid percentage' => [['coverage', '--min-coverage=not-a-number'], 2],
            'unverified spec' => [['spec'], 0],
            'strict unverified spec' => [['spec', '--strict'], 1],
        ];
    }
}
