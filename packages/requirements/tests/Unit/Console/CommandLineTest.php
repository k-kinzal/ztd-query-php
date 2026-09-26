<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

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
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\Executor;
use Requirements\Console\Options;
use Requirements\Console\SpecificationReport;
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
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\TextSource;
use Requirements\Test\Registry as TestRegistry;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fake\ProjectDirectory;

#[CoversClass(CommandLine::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(Options::class)]
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
#[UsesClass(Executor::class)]
#[UsesClass(SpecificationReport::class)]
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
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(SourceRegistry::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(TestRegistry::class)]
#[Small]
final class CommandLineTest extends TestCase
{
    public function testCreateNamesTheApplicationAndLeavesExitAndErrorsToTheCaller(): void
    {
        $console = (new CommandLine())->create();
        self::assertSame('Requirements', $console->getName());
        self::assertFalse($console->isAutoExitEnabled());
        self::assertFalse($console->areExceptionsCaught());
    }

    #[DataProvider('providerCreateCommands')]
    public function testCreateRegistersEveryCommand(string $name, string $description): void
    {
        $command = (new CommandLine())->create()->find($name);
        self::assertSame($name, $command->getName());
        self::assertSame($description, $command->getDescription());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerCreateCommands(): array
    {
        return [
            'check' => ['check', 'Verify quotations against the declared source scopes.'],
            'coverage' => ['coverage', 'Show source coverage and enforce total and differential gates.'],
            'spec' => ['spec', 'Browse specifications and requirements, and verify linked tests unless --no-test is set.'],
            'lint' => ['lint', 'Validate schemas, EARS syntax and cross-file traceability.'],
            'format' => ['format', 'Format YAML and Markdown definition documents.'],
        ];
    }

    public function testCreateDeclaresTheGlobalOptions(): void
    {
        $definition = (new CommandLine())->create()->getDefinition();
        $help = $definition->getOption('help');
        self::assertSame('h', $help->getShortcut());
        self::assertFalse($help->acceptValue());
        self::assertSame('Display command help, or the application overview when no command is given.', $help->getDescription());
        $config = $definition->getOption('config');
        self::assertSame('c', $config->getShortcut());
        self::assertTrue($config->isValueRequired());
        self::assertSame('requirements.yaml', $config->getDefault());
        self::assertSame('Path to the YAML project configuration.', $config->getDescription());
        $json = $definition->getOption('json');
        self::assertNull($json->getShortcut());
        self::assertFalse($json->acceptValue());
        self::assertSame('Write a JSON report to stdout instead of terminal tables.', $json->getDescription());
    }

    /**
     * @throws ExceptionInterface
     */
    public function testCreateRunsACommandThroughItsHandler(): void
    {
        $project = new ProjectDirectory();
        $output = new BufferedOutput();
        self::assertSame(0, (new CommandLine())->create()->find('lint')->run(new ArrayInput(['--config' => $project->path('requirements.yaml'), '--json' => true]), $output));
        self::assertSame("{\n    \"passed\": true,\n    \"message\": \"1 items validated.\"\n}\n", $output->fetch());
    }

    public function testCommandDeclaresTheNameDescriptionAndHelp(): void
    {
        $command = (new CommandLine())->command('check', 'Verify quotations.');
        self::assertSame('check', $command->getName());
        self::assertSame('Verify quotations.', $command->getDescription());
        self::assertSame("Run <info>requirements check --config requirements.yaml</info>.\n\nExit codes: 0 success, 1 failed check/gate/spec/format, 2 invalid configuration or execution error.", $command->getHelp());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerCommandOptions')]
    public function testCommandDeclaresTheOptionsOfTheCommand(string $name, array $expected): void
    {
        self::assertSame($expected, array_keys((new CommandLine())->command($name, '')->getDefinition()->getOptions()));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function providerCommandOptions(): array
    {
        return [
            'spec' => ['spec', ['no-test', 'strict', 'all', 'id', 'label', 'category', 'source', 'status', 'kind', 'origin', 'without-source']],
            'check' => ['check', ['live']],
            'coverage' => ['coverage', ['live', 'snapshot', 'write-snapshot', 'min-coverage', 'min-diff-coverage', 'allow-removed']],
            'format' => ['format', ['check']],
            'lint' => ['lint', []],
        ];
    }

    public function testCommandDeclaresFlagsWithoutValuesAndOtherOptionsWithRequiredValues(): void
    {
        $definition = (new CommandLine())->command('spec', '')->getDefinition();
        $flag = $definition->getOption('no-test');
        self::assertFalse($flag->acceptValue());
        self::assertNull($flag->getShortcut());
        self::assertSame('Display selected records and linked test counts without running tests.', $flag->getDescription());
        $value = $definition->getOption('id');
        self::assertTrue($value->isValueRequired());
        self::assertNull($value->getShortcut());
        self::assertSame('Select an exact item ID.', $value->getDescription());
        self::assertFalse($definition->getOption('without-source')->acceptValue());
        self::assertTrue($definition->getOption('label')->isValueRequired());
    }
}
