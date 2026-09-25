<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Requirements\Console\Executor;
use Requirements\Console\ItemRecord;
use Requirements\Console\Options;
use Requirements\Console\Reporter;
use Requirements\Console\SpecificationTable;
use Requirements\Console\Text;
use Requirements\Console\Verdict;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\LiteralMask;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Validator;
use Requirements\Ears\Wording;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
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
use Requirements\Verification\VerificationResult;
use Requirements\Verification\Verifier;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fake\ProjectDirectory;

#[CoversClass(CommandHandler::class)]
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
#[UsesClass(Options::class)]
#[UsesClass(Reporter::class)]
#[UsesClass(SpecificationTable::class)]
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
#[UsesClass(Coverage::class)]
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
#[UsesClass(Verifier::class)]
#[UsesClass(ItemRecord::class)]
#[UsesClass(VerificationResult::class)]
#[Small]
final class CommandHandlerTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testInvokeWritesThePrettyJsonReport(): void
    {
        $project = new ProjectDirectory();
        $definition = new InputDefinition([new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'), new InputOption('json', null, InputOption::VALUE_NONE)]);
        $output = new BufferedOutput();
        self::assertSame(0, (new CommandHandler('lint'))(new ArrayInput(['--config' => $project->path('requirements.yaml'), '--json' => true], $definition), $output));
        self::assertSame("{\n    \"passed\": true,\n    \"message\": \"1 items validated.\"\n}\n", $output->fetch());
    }

    /**
     * @throws JsonException
     */
    public function testInvokeWritesUnescapedSlashesAndUnicode(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [[...ProjectDirectory::item(), 'category' => 'Größe/Parser <b>']]]);
        $definition = new InputDefinition([
            new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'),
            new InputOption('json', null, InputOption::VALUE_NONE),
            ...array_map(static fn (array $option): InputOption => new InputOption($option[0], null, $option[1] ? InputOption::VALUE_NONE : InputOption::VALUE_REQUIRED), Options::definitions('spec')),
        ]);
        $output = new BufferedOutput();
        self::assertSame(0, (new CommandHandler('spec'))(new ArrayInput(['--config' => $project->path('requirements.yaml'), '--json' => true, '--no-test' => true], $definition), $output));
        self::assertStringContainsString("\n            \"category\": \"Größe/Parser <b>\",\n", $output->fetch());
    }

    /**
     * @throws JsonException
     */
    public function testInvokeRendersTheTerminalReport(): void
    {
        $project = new ProjectDirectory();
        $definition = new InputDefinition([new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'), new InputOption('json', null, InputOption::VALUE_NONE)]);
        $output = new BufferedOutput();
        self::assertSame(0, (new CommandHandler('lint'))(new ArrayInput(['--config' => $project->path('requirements.yaml')], $definition), $output));
        $text = $output->fetch();
        self::assertStringContainsString("Requirements · lint\n===================\n", $text);
        self::assertStringContainsString('[OK] 1 items validated.', $text);
        self::assertStringNotContainsString('"passed"', $text);
    }

    /**
     * @throws JsonException
     */
    public function testInvokeReturnsOneForAFailedJsonReport(): void
    {
        $project = new ProjectDirectory();
        $definition = new InputDefinition([
            new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'),
            new InputOption('json', null, InputOption::VALUE_NONE),
            ...array_map(static fn (array $option): InputOption => new InputOption($option[0], null, $option[1] ? InputOption::VALUE_NONE : InputOption::VALUE_REQUIRED), Options::definitions('coverage')),
        ]);
        $output = new BufferedOutput();
        self::assertSame(1, (new CommandHandler('coverage'))(new ArrayInput(['--config' => $project->path('requirements.yaml'), '--json' => true, '--min-coverage' => '100'], $definition), $output));
        self::assertStringContainsString('"passed": false', $output->fetch());
    }

    /**
     * @throws JsonException
     */
    public function testInvokeReturnsOneForAFailedTerminalReport(): void
    {
        $project = new ProjectDirectory();
        $definition = new InputDefinition([
            new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'),
            new InputOption('json', null, InputOption::VALUE_NONE),
            ...array_map(static fn (array $option): InputOption => new InputOption($option[0], null, $option[1] ? InputOption::VALUE_NONE : InputOption::VALUE_REQUIRED), Options::definitions('spec')),
        ]);
        $output = new BufferedOutput();
        self::assertSame(1, (new CommandHandler('spec'))(new ArrayInput(['--config' => $project->path('requirements.yaml'), '--id' => 'MISSING'], $definition), $output));
        self::assertStringContainsString('[ERROR] No specifications or requirements selected.', $output->fetch());
    }

    /**
     * @throws JsonException
     */
    public function testInvokeRejectsAMissingConfiguration(): void
    {
        $project = new ProjectDirectory();
        $definition = new InputDefinition([new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'), new InputOption('json', null, InputOption::VALUE_NONE)]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Configuration does not exist');
        (new CommandHandler('lint'))(new ArrayInput(['--config' => $project->path('missing.yaml'), '--json' => true], $definition), new BufferedOutput());
    }
}
