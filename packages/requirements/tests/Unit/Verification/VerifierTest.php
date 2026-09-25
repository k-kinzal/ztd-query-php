<?php

declare(strict_types=1);

namespace Tests\Unit\Verification;

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
use Requirements\Model\TestReference;
use Requirements\Report\Analysis;
use Requirements\Report\Analyzer;
use Requirements\Report\Claims;
use Requirements\Report\EvidenceMatcher;
use Requirements\Report\SourceUnit;
use Requirements\Report\UnitCollector;
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use Requirements\Test\BehatRunner;
use Requirements\Test\PhpUnitRunner;
use Requirements\Test\Registry;
use Requirements\Test\RunnerConfig;
use Requirements\Test\TestResult;
use Requirements\Verification\VerificationResult;
use Requirements\Verification\Verifier;
use stdClass;
use Tests\Fake\CountingRunner;
use Tests\Fake\MemorySource;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Verifier::class)]
#[UsesClass(VerificationResult::class)]
#[UsesClass(Registry::class)]
#[UsesClass(PhpUnitRunner::class)]
#[UsesClass(BehatRunner::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(TestResult::class)]
#[UsesClass(Project::class)]
#[UsesClass(Item::class)]
#[UsesClass(TestReference::class)]
#[UsesClass(Loader::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(CoverageThresholds::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(ExtensionClasses::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(LinkValidator::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Wording::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(ItemValidator::class)]
#[UsesClass(Source::class)]
#[UsesClass(Analysis::class)]
#[UsesClass(Claims::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(UnitCollector::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(Unit::class)]
#[UsesClass(SourceRegistry::class)]
#[Small]
final class VerifierTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testVerifyRunsCustomSourcesAndRunnersThroughTheSamePipelineAndDeduplicatesTests(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'extensions' => ['sources' => ['service' => MemorySource::class], 'runners' => ['custom' => CountingRunner::class]],
            'runners' => ['example' => ['extension' => 'custom', 'command' => ['example']]],
        ]);
        $item = ['id' => 'SPEC-1', 'statement' => 'The service shall retain its message.', 'evidence' => [['selector' => 'message:1', 'quote' => 'A service message.']], 'tests' => [['runner' => 'example', 'target' => 'shared']]];
        $second = [...$item, 'id' => 'SPEC-2'];
        $unsupported = [...$item, 'id' => 'SPEC-3', 'status' => 'unsupported', 'reason' => 'The external system owns this behavior.', 'tests' => [['runner' => 'example', 'target' => 'never']]];
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'service', 'uri' => 'service:channel', 'format' => 'service', 'selector' => '*'], 'items' => [$item, $second, $unsupported]]);
        $loaded = (new Loader())->load($project->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($loaded);
        self::assertSame([], $analysis->errors);
        self::assertSame(100.0, $analysis->summary()['percentage']);
        $results = (new Verifier())->verify($loaded, $loaded->items);
        self::assertSame('passed', $results['SPEC-1']->status);
        self::assertSame('passed', $results['SPEC-2']->status);
        self::assertSame(1, $results['SPEC-1']->passedTargets);
        self::assertSame(1, $results['SPEC-2']->totalTargets);
        self::assertSame('unsupported', $results['SPEC-3']->status);
        self::assertSame("shared\n", $project->read('executions.txt'));
    }

    /**
     * @throws JsonException
     */
    public function testVerifyDoesNotCountAZeroCaseSuccessAsAPassingTarget(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'extensions' => ['runners' => ['custom' => CountingRunner::class]],
            'runners' => ['example' => ['extension' => 'custom', 'command' => ['example']]],
        ]);
        $project->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [[
            'id' => 'SPEC-1', 'statement' => 'The reader shall retain positions.', 'origin' => 'original', 'reason' => 'Support editors.',
            'tests' => [['runner' => 'example', 'target' => 'empty']],
        ]]]);
        $loaded = (new Loader())->load($project->directory . '/requirements.yaml');
        $result = (new Verifier())->verify($loaded, $loaded->items)['SPEC-1'];
        self::assertSame('failed', $result->status);
        self::assertSame(0, $result->passedTargets);
        self::assertSame(1, $result->totalTargets);
        self::assertSame(0, $result->tests);
    }

    public function testVerifyReportsRequirementsAsNotApplicableWithoutRunningThem(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('custom', ['example'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $item = new Item('REQ-1', 'requirement', 'Names start with letters', 'unsupported', null, [], [new TestReference('example', 'a'), new TestReference('example', 'a'), new TestReference('example', 'b')], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['REQ-1' => $item]);
        self::assertSame(['status' => 'not-applicable', 'tests' => 0, 'passed_targets' => null, 'total_targets' => 2, 'message' => ''], $results['REQ-1']->toArray());
        self::assertFileDoesNotExist($directory->path('executions.txt'));
    }

    public function testVerifyReportsUnsupportedSpecificationsWithoutRunningThem(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('custom', ['example'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'unsupported', null, [], [new TestReference('example', 'a')], [], [], [], '', 'original', 'Owned elsewhere.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $item], true);
        self::assertSame(['status' => 'unsupported', 'tests' => 0, 'passed_targets' => null, 'total_targets' => 1, 'message' => ''], $results['SPEC-1']->toArray());
        self::assertFileDoesNotExist($directory->path('executions.txt'));
    }

    public function testVerifyReportsLinkedTargetsAsNotRunWithoutTests(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('custom', ['example'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $linked = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('example', 'a'), new TestReference('example', 'b')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $unlinked = new Item('SPEC-2', 'specification', 'The reader shall read.', 'supported', null, [], [], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $linked, 'SPEC-2' => $unlinked], true);
        self::assertSame(['status' => 'not-run', 'tests' => 0, 'passed_targets' => null, 'total_targets' => 2, 'message' => ''], $results['SPEC-1']->toArray());
        self::assertSame(['status' => 'not-run', 'tests' => 0, 'passed_targets' => null, 'total_targets' => 0, 'message' => ''], $results['SPEC-2']->toArray());
        self::assertFileDoesNotExist($directory->path('executions.txt'));
    }

    public function testVerifyReportsSpecificationsWithoutTestsAsUnverified(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], [], [], [], 0.0, 0.0, [], []);
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $item]);
        self::assertSame(['status' => 'unverified', 'tests' => 0, 'passed_targets' => 0, 'total_targets' => 0, 'message' => 'No tests linked.'], $results['SPEC-1']->toArray());
    }

    public function testVerifyPassesWhenEveryTargetPasses(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('custom', ['example'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('example', 'a'), new TestReference('example', 'b')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $item]);
        self::assertSame(['status' => 'passed', 'tests' => 2, 'passed_targets' => 2, 'total_targets' => 2, 'message' => ''], $results['SPEC-1']->toArray());
        self::assertSame("a\nb\n", $directory->read('executions.txt'));
    }

    public function testVerifyFailsWhenAnyTargetFails(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('custom', ['example'], $directory->directory), 'unit' => new RunnerConfig('phpunit', [PHP_BINARY], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('unit', 'malformed'), new TestReference('example', 'a'), new TestReference('example', 'empty')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $item]);
        self::assertSame(['status' => 'failed', 'tests' => 1, 'passed_targets' => 1, 'total_targets' => 3, 'message' => "malformed: PHPUnit targets must be fully qualified Class::method references.\nempty: "], $results['SPEC-1']->toArray());
        self::assertSame("a\nempty\n", $directory->read('executions.txt'));
    }

    public function testVerifyRunsASharedTargetOnce(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('custom', ['example'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $first = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('example', 'shared'), new TestReference('example', 'shared')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $second = new Item('SPEC-2', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('example', 'shared'), new TestReference('example', 'own')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $first, 'SPEC-2' => $second]);
        self::assertSame(['status' => 'passed', 'tests' => 1, 'passed_targets' => 1, 'total_targets' => 1, 'message' => ''], $results['SPEC-1']->toArray());
        self::assertSame(['status' => 'passed', 'tests' => 2, 'passed_targets' => 2, 'total_targets' => 2, 'message' => ''], $results['SPEC-2']->toArray());
        self::assertSame("shared\nown\n", $directory->read('executions.txt'));
    }

    public function testVerifyRunsTheSameTargetOfDifferentRunnersSeparately(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['first' => new RunnerConfig('custom', ['first'], $directory->directory), 'second' => new RunnerConfig('custom', ['second'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('first', 'a'), new TestReference('second', 'a')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['SPEC-1' => $item]);
        self::assertSame(['status' => 'passed', 'tests' => 2, 'passed_targets' => 2, 'total_targets' => 2, 'message' => ''], $results['SPEC-1']->toArray());
        self::assertSame("a\na\n", $directory->read('executions.txt'));
    }

    public function testVerifyKeysResultsByItemId(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], [], [], [], 0.0, 0.0, [], []);
        $requirement = new Item('REQ-1', 'requirement', 'Names start with letters', 'supported', null, [], [], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $specification = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $results = (new Verifier())->verify($project, ['first' => $requirement, 'second' => $specification]);
        self::assertSame(['REQ-1', 'SPEC-1'], array_keys($results));
    }

    public function testVerifyReturnsNothingWithoutItems(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], [], [], [], 0.0, 0.0, [], []);
        self::assertSame([], (new Verifier())->verify($project, []));
    }

    public function testVerifyRejectsAnUnknownRunnerExtension(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['example' => new RunnerConfig('jest', ['jest'], $directory->directory)], [], [], 0.0, 0.0, [], []);
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('example', 'a')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown runner extension: jest');
        (new Verifier())->verify($project, ['SPEC-1' => $item]);
    }

    public function testVerifyRejectsAnExtensionClassThatIsNotARunner(): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], [], [], ['custom' => stdClass::class], 0.0, 0.0, [], []);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('stdClass must implement RunnerExtension.');
        (new Verifier())->verify($project, []);
    }
}
