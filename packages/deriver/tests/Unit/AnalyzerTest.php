<?php

declare(strict_types=1);

namespace Tests\Unit;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Execution\Derivation;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Analyzer::class)]
#[UsesClass(\Deriver\Query::class)]
#[UsesClass(\Deriver\Analysis\Selection\SelectorResolution::class)]
#[UsesClass(\Deriver\Analysis\Execution\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\Selection\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Execution\ResultAssessment::class)]
#[UsesClass(\Deriver\Session\Session::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Builtins::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Materialization::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
#[UsesClass(\Deriver\Evaluation\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Evaluation\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderExpansion::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Execution\Alternative::class)]
#[UsesClass(\Deriver\Result\Execution\Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(\Deriver\Result\Execution\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Execution\Exceptional::class)]
#[UsesClass(\Deriver\Diagnostic\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\Execution\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class AnalyzerTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeReadsPathsAndDoesNotExecuteApplicationCode(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php throw new RuntimeException("never execute"); function target() { $x="before"; $x="after"; return $x . ":done"; }');
        $result = (new Analyzer())->analyze([$path], 'target#return');
        self::assertSame('after:done', $result[0]->result);
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($result));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeUsesOnlySelectedFilesAndReadsThemAfterDiscovery(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $source->write('target.php', '<?php function target(){return helper();}');
        $source->write('helper.php', '<?php function helper(){return 1;}');
        $source->write('excluded/broken.php', '<?php function broken(');
        $files = (new \Deriver\Finder())->in($source->path)->include('**/*.php')->exclude('excluded/**')->files();
        $source->write('helper.php', '<?php function helper(){return 42;}');
        $result = (new Analyzer())->analyze($files, 'target#return');
        self::assertSame(42, $result[0]->result);
        self::assertCount(2, $result[0]->evidence[0]->snapshot->sources);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeInvalidatesMissingDeclarationsWhenFilesAreAdded(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){return missing();}');
        $analyzer = new Analyzer();
        $query = 'target#return';
        $before = $analyzer->analyze([$path], $query);
        $added = $source->write('added.php', '<?php function missing(){return 7;}');
        $after = $analyzer->analyze([$path, $added], $query);
        self::assertNotEmpty(\Tests\Semantic\CandidateContractTest::frontiers($before));
        self::assertSame(7, $after[0]->result);
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($after));
        self::assertNotSame($before[0]->evidence[0]->snapshot->id, $after[0]->evidence[0]->snapshot->id);
        self::assertSame($before->toJson(), $analyzer->analyze([$path], $query)->toJson());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeKeepsWarmClosureSemanticsAndNewSnapshotReferences(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){$f=fn()=>7;return $f();}');
        $analyzer = new Analyzer();
        $query = 'target#return';
        $first = $analyzer->analyze([$path], $query);
        $added = $source->write('added.php', '<?php function unrelated(){}');
        $result = $analyzer->analyze([$path, $added], $query);
        self::assertSame(7, $result[0]->result);
        $manifest = $result[0]->evidence[0]->snapshot;
        self::assertNotSame($first[0]->evidence[0]->snapshot->id, $manifest->id);
        self::assertSame([$manifest->id], array_values(array_unique(array_map(static fn ($node): ?string => $node->source?->snapshotId, array_filter($result[0]->evidence[0]->nodes(), static fn ($node): bool => $node->source !== null)))));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeReadsChangedContentsOnEveryCall(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){return 1;}');
        $analyzer = new Analyzer();
        $query = 'target#return';
        $first = $analyzer->analyze([$path], $query);
        $source->write('target.php', '<?php function target(){return 2;}');
        $second = $analyzer->analyze([$path], $query);
        self::assertSame(1, $first[0]->result);
        self::assertSame(2, $second[0]->result);
        self::assertNotSame($first->toJson(), $second->toJson());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzePreservesConfigurationAndRuleReplacements(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){return count(unknown());}');
        $configuration = new \Deriver\Project\Configuration(expansionRules: [
            \Deriver\Project\Expansion\Rule::constantFunction('count-one', '1', 'count', \Deriver\Value\Term::constant(1)),
        ]);
        $result = (new Analyzer($configuration))->analyze([$path], 'target#return');
        self::assertSame(1, $result[0]->result);
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($result));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeRejectsMalformedSource(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('broken.php', '<?php function broken(');
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        (new Analyzer())->analyze([$path], 'target#return');
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeRejectsFilesRemovedAfterDiscovery(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){return 1;}');
        $files = (new \Deriver\Finder())->in($source->path)->files();
        self::assertTrue(unlink($path));
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        (new Analyzer())->analyze($files, 'target#return');
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeRejectsDirectoriesInsteadOfExpandingThem(): void
    {
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        (new Analyzer())->analyze([__DIR__], 'target#return');
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeRejectsRepeatedPaths(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){return 1;}');
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        (new Analyzer())->analyze([$path, $path], 'target#return');
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAnalyzeRetainsEvidenceAfterTheAnalyzerIsReleased(): void
    {
        $source = new \Tests\Fake\SourceDirectory();
        $path = $source->write('target.php', '<?php function target(){return 42;}');
        $result = (new Analyzer())->analyze([$path], 'target#return');
        $configuration = new \Deriver\Project\Configuration();
        (new \Deriver\Replay())->verify($result->toJson(), ProjectInput::fromFiles([$path]), $configuration, new ReturnQuery('target'));
        self::assertSame(42, $result[0]->result);
    }
}
