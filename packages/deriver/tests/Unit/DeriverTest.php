<?php

declare (strict_types=1);

namespace Tests\Unit;

use Deriver\Deriver;
use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\Exceptional;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Cell;
use Deriver\Deriver\Evaluator\Execution\Demand\Components;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Demand\Key;
use Deriver\Deriver\Evaluator\Execution\Demand\Table;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\CallableTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Query;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\Selection\SelectorResolution;
use Deriver\Deriver\Sources\Collector;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\CollectorException;
use Deriver\Exception\InvalidInputException;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use WeakMap;
use WeakReference;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Deriver::class)]
#[UsesClass(Query::class)]
#[UsesClass(SelectorResolution::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Library::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Collector::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DeriverTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testOnlyPreparationAndDerivationArePublic(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $deriver = Deriver::prepare([]);
        $methods = get_class_methods($deriver);
        sort($methods);
        self::assertSame(['derive', 'prepare'], $methods);
        self::assertSame([], get_object_vars($deriver));
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareUsesDefaultConfiguration(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'default.php';
        $contents = '<?php function target(){return 42;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $implicit = Deriver::prepare([$path])->derive('target#return');
        $explicit = Deriver::prepare([$path], DeriverConfig::default())->derive('target#return');
        self::assertEquals(iterator_to_array($implicit), iterator_to_array($explicit));
        self::assertSame('8.3', $implicit[0]->evidence[0]->snapshot->target->version);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareTranslatesThePhpVersionIntoParserConfiguration(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'version.php';
        $contents = '<?php class Box{function value(){return 42;}} function target(){return new Box()->value();}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $result = Deriver::prepare([$path], new DeriverConfig(phpVersion: '8.4'))->derive('target#return');
        self::assertSame(42, $result[0]->result);
        self::assertSame('8.4', $result[0]->evidence[0]->snapshot->target->version);
        $this->expectException(ParserException::class);
        Deriver::prepare([$path]);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareAndDeriveDoNotExecuteApplicationCode(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php throw new RuntimeException("never execute"); function target() { $x="before"; $x="after"; return $x . ":done"; }';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path]);
        $result = $deriver->derive('target#return');
        self::assertSame('after:done', $result[0]->result);
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source1 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source1, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareReadsOnlyTheSelectedFilesAfterDiscovery(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project', null, ['excluded' => []]);
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return helper();}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $target = '/' . $source->path() . '/' . $name;
        $name1 = 'helper.php';
        $contents1 = '<?php function helper(){return 1;}';
        $url1 = $source->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $helper = '/' . $source->path() . '/' . $name1;
        $name2 = 'excluded/broken.php';
        $contents2 = '<?php function broken(';
        $url2 = $source->url() . '/' . $name2;
        file_put_contents($url2, $contents2);
        $files = [$target, $helper];
        $name3 = 'helper.php';
        $contents3 = '<?php function helper(){return 42;}';
        $url3 = $source->url() . '/' . $name3;
        file_put_contents($url3, $contents3);
        $result = Deriver::prepare($files)->derive('target#return');
        self::assertSame(42, $result[0]->result);
        self::assertCount(2, $result[0]->evidence[0]->snapshot->sources);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPreparedSourcesSurviveChangesAndRemovalBeforeTheFirstQuery(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'first.php';
        $contents = '<?php function first(){return 1;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $firstPath = '/' . $source->path() . '/' . $name;
        $name1 = 'second.php';
        $contents1 = '<?php function second(){return 2;}';
        $url1 = $source->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $secondPath = '/' . $source->path() . '/' . $name1;
        $deriver = Deriver::prepare([$firstPath, $secondPath]);
        $name2 = 'first.php';
        $contents2 = '<?php function first(){return 99;}';
        $url2 = $source->url() . '/' . $name2;
        file_put_contents($url2, $contents2);
        $path = $secondPath;
        $path = str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path;
        self::assertTrue(unlink('vfs:/' . Path::normalize($path)));
        self::assertSame(1, $deriver->derive('first#return')[0]->result);
        self::assertSame(2, $deriver->derive('second#return')[0]->result);
        self::assertSame(1, $deriver->derive('first#return')[0]->result);
        self::assertSame(99, Deriver::prepare([$firstPath])->derive('first#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testRepeatedQueriesHaveIndependentEvaluationState(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function increment(){static $n=0;return ++$n;} function first(){return increment();} function second(){return increment()+10;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path]);
        $first = $deriver->derive('first#return');
        self::assertSame(1, $first[0]->result);
        self::assertSame(11, $deriver->derive('second#return')[0]->result);
        $again = $deriver->derive('first#return');
        self::assertSame(1, $again[0]->result);
        self::assertNotSame($first, $again);
        self::assertNotSame($first[0], $again[0]);
        self::assertEquals(iterator_to_array($first), iterator_to_array($again));
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriverDoesNotRetainResults(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return 42;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path]);
        $result = $deriver->derive('target#return');
        $reference = WeakReference::create($result);
        $candidate = WeakReference::create($result[0]);
        unset($result);
        self::assertNull($reference->get());
        self::assertNull($candidate->get());
        self::assertSame(42, $deriver->derive('target#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testRejectedQueryDoesNotInvalidatePreparedSources(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return 42;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path]);
        try {
            $deriver->derive('missing#return');
            self::fail('An absent target must be rejected.');
        } catch (InvalidInputException $error) {
            self::assertStringContainsString('missing', $error->getMessage());
        }
        self::assertSame(42, $deriver->derive('target#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testNewPreparationIncludesAddedDeclarations(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return missing();}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path]);
        $before = $deriver->derive('target#return');
        $name1 = 'added.php';
        $contents1 = '<?php function missing(){return 7;}';
        $url1 = $source->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $added = '/' . $source->path() . '/' . $name1;
        $after = Deriver::prepare([$path, $added])->derive('target#return');
        $frontiers = array_reduce(iterator_to_array($before, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source1 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source1, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertNotEmpty($frontiers);
        self::assertSame(7, $after[0]->result);
        $frontiers2 = array_reduce(iterator_to_array($after, false), static function (array $frontiers2, Candidate $candidate1): array {
            if ($candidate1->result instanceof Term) {
                $graph1 = $candidate1->result;
                $snapshot1 = $candidate1->evidence[0]->snapshot;
                $pending1 = [$graph1];
                $seen1 = new WeakMap();
                $frontiers3 = [];
                while ($pending1 !== []) {
                    $node1 = array_pop($pending1);
                    if (isset($seen1[$node1])) {
                        continue;
                    }
                    $seen1[$node1] = true;
                    array_push($pending1, ...array_values($node1->operands));
                    $reason1 = $node1->attributes['reason'] ?? null;
                    if (!is_string($reason1)) {
                        continue;
                    }
                    $source2 = new SourceRef($snapshot1->id, (string) ($node1->attributes['source'] ?? ''), (int) ($node1->attributes['start'] ?? 0), (int) ($node1->attributes['end'] ?? $node1->attributes['start'] ?? 0));
                    $identity1 = (string) ($node1->attributes['identity'] ?? (new Identity())->key($node1));
                    $frontiers3[$identity1] = new Frontier($reason1, $source2, (string) $node1->literal, ['value'], [$identity1], $node1, $reason1);
                }
                array_push($frontiers2, ...array_values($frontiers3));
            }
            return $frontiers2;
        }, []);
        self::assertSame([], $frontiers2);
        self::assertNotSame($before[0]->evidence[0]->snapshot->id, $after[0]->evidence[0]->snapshot->id);
        self::assertEquals(iterator_to_array($before), iterator_to_array($deriver->derive('target#return')));
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testNewPreparationKeepsClosureReferencesWithinItsSnapshot(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){$f=fn()=>7;return $f();}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $first = Deriver::prepare([$path])->derive('target#return');
        $name1 = 'added.php';
        $contents1 = '<?php function unrelated(){}';
        $url1 = $source->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $added = '/' . $source->path() . '/' . $name1;
        $result = Deriver::prepare([$path, $added])->derive('target#return');
        self::assertSame(7, $result[0]->result);
        $manifest = $result[0]->evidence[0]->snapshot;
        self::assertNotSame($first[0]->evidence[0]->snapshot->id, $manifest->id);
        self::assertSame([$manifest->id], array_values(array_unique(array_map(static fn ($node): ?string => $node->source?->snapshotId, array_filter($result[0]->evidence[0]->nodes(), static fn ($node): bool => $node->source !== null)))));
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareConvertsConfiguredMicroEvaluators(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return count(unknown());}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path], new DeriverConfig(evaluators: [new MicroEvaluator('count-one', '1', EvaluationOperation::Function, 'count', static fn (): Term => Term::constant(1))]));
        $result = $deriver->derive('target#return');
        self::assertSame(1, $result[0]->result);
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source1 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source1, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareRejectsMalformedSourceWithoutAQuery(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'broken.php';
        $contents = '<?php function broken(';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $this->expectException(ParserException::class);
        Deriver::prepare([$path]);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareReportsResolutionFailuresSeparatelyFromSyntaxErrors(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'scope.php';
        $contents = '<?php function target() { return self::class; }';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('scope.php:1:');
        Deriver::prepare([$path]);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareRejectsFilesRemovedAfterDiscovery(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return 1;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $files = [$path];
        $path1 = $path;
        $path1 = str_starts_with($path1, '/') ? $path1 : $workingDirectory . '/' . $path1;
        self::assertTrue(unlink('vfs:/' . Path::normalize($path1)));
        $this->expectException(CollectorException::class);
        Deriver::prepare($files);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareRejectsDirectoriesInsteadOfExpandingThem(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $this->expectException(CollectorException::class);
        Deriver::prepare([__DIR__]);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareRejectsRepeatedPaths(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return 1;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $this->expectException(CollectorException::class);
        Deriver::prepare([$path, $path]);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCandidateEvidenceSurvivesTheDeriver(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return 42;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $result = Deriver::prepare([$path])->derive('target#return');
        $proof = $result[0]->evidence[0];
        $path1 = $path;
        $path1 = str_starts_with($path1, '/') ? $path1 : $workingDirectory . '/' . $path1;
        self::assertSame(hash_file('sha256', 'vfs:/' . Path::normalize($path1)), $proof->snapshot->sources[$path]);
        self::assertSame($proof->root, $proof->nodes()[0]);
        self::assertSame(42, $result[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPrepareRejectsIncompleteDeclarations(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'target.php';
        $contents = '<?php class C { use MissingTrait; }';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $this->expectException(InvalidInputException::class);
        Deriver::prepare([$path]);
    }
    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
