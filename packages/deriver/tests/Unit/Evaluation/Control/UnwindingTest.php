<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Constants;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\ProviderDispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchRequest;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Exceptional;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\Control\LoopLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(Unwinding::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(LoopConvergence::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(LoopLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class UnwindingTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testResumePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=0;while(true){try{break;}finally{$x=2;}}return $x;}');
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testRoutesRunsFinallyBeforeAnEscapingThrowable(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function fail(&$x){try{throw new Exception();}finally{$x=2;}}function target(){$x=1;try{fail($x);}catch(Exception $e){return $x;}return 999;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testCaptureTurnsRuntimeErrorsIntoInspectableObjects(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $object = (new Unwinding($context->program))->capture($state, new Term('throwable', 'TypeError'));
        self::assertSame('object', $object->kind);
        self::assertSame('TypeError', $object->attributes['class']);
        self::assertSame(0, $state->memory->read(new Location('object:'.$object->literal, ['code']))->native());
        self::assertSame('RUNTIME_DIAGNOSTIC', $state->memory->read(new Location('object:'.$object->literal, ['message']))->literal);
    }
    public function testCaptureDoesNotInventAConcreteClassForAnUnknownThrowable(): void
    {
        $context = SolverFixture::context();
        $object = (new Unwinding($context->program))->capture(new State(), new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        self::assertSame('object', $object->kind);
        self::assertArrayNotHasKey('class', $object->attributes);
        self::assertTrue($object->attributes['uncertain']);
    }

    /**
     * @param string $source Captured PHP declaration with a symbolic typed input
     * @param list<int> $expected Reachable return values
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[DataProvider('providerSymbolicThrows')]
    public function testThrowRoutesUsesThrowableTypeBoundsAndCatchOrder(string $source, array $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        $values = array_column(array_column(array_column($result->normalOutcomes, 'values'), 'return'), 'literal');
        sort($values);
        self::assertSame($expected, $values);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @return iterable<string,array{string,list<int>}>
     */
    public static function providerSymbolicThrows(): iterable
    {
        yield 'exception parent' => ['<?php function target(RuntimeException $e){try{throw $e;}catch(Exception $caught){return 1;}return 2;}',[1]];
        yield 'error parent' => ['<?php function target(TypeError $e){try{throw $e;}catch(Error $caught){return 1;}return 2;}',[1]];
        yield 'unrelated catch excluded' => ['<?php function target(RuntimeException $e){try{throw $e;}catch(Error $caught){return 1;}catch(Exception $caught){return 2;}}',[2]];
        yield 'possible subclass' => ['<?php function target(Exception $e){try{throw $e;}catch(RuntimeException $caught){return 1;}catch(Exception $caught){return 2;}}',[1,2]];
        yield 'first parent shadows subclass' => ['<?php function target(Exception $e){try{throw $e;}catch(Exception $caught){return 1;}catch(RuntimeException $caught){return 2;}}',[1]];
        yield 'union arms caught' => ['<?php function target(RuntimeException|TypeError $e){try{throw $e;}catch(Exception $caught){return 1;}catch(Error $caught){return 2;}}',[1,2]];
        yield 'multi catch covers union' => ['<?php function target(RuntimeException|TypeError $e){try{throw $e;}catch(RuntimeException|TypeError $caught){return 1;}}',[1]];
        yield 'custom subclass' => ['<?php class Problem extends RuntimeException{}function target(Problem $e){try{throw $e;}catch(LogicException $caught){return 1;}catch(RuntimeException $caught){return 2;}}',[2]];
        yield 'primitive typed throw' => ['<?php function target(int $e){try{throw $e;}catch(Error $caught){return 1;}return 2;}',[1]];
        yield 'known nonthrowable typed object' => ['<?php class Box{}function target(Box $e){try{throw $e;}catch(Error $caught){return 1;}return 2;}',[1]];
        yield 'rethrow keeps type' => ['<?php function target(RuntimeException $e){try{try{throw $e;}catch(Exception $caught){throw $caught;}}catch(RuntimeException $outer){return 1;}return 2;}',[1]];
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testRoutesKeepsPossibleCatchesAndAnUncaughtThrowableRemainder(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(Throwable $e){try{throw $e;}catch(RuntimeException $caught){return 1;}catch(Error $caught){return 2;}}');
        $values = array_column(array_column(array_column($result->normalOutcomes, 'values'), 'return'), 'literal');
        sort($values);
        self::assertSame([1,2], $values);
        self::assertNotEmpty($result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @param string $source Trusted concrete exception fixture
     * @param string $expectedJson Expected return including previous-exception observations
     * @throws JsonException If expected values cannot be decoded
     */
    #[DataProvider('providerExceptionPrograms')]
    public function testResumeMatchesConcreteThrowValidationAndFinallyChaining(string $source, string $expectedJson): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerExceptionPrograms(): array
    {
        return \Tests\Fake\Programs\ExceptionPrograms::cases();
    }

    #[DataProvider('providerPendingCompletion')]
    public function testResumeSavesThePendingCompletionBeforeEnteringFinally(string $phase, string $kind): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $region = new ExceptionRegion([], 9, 10);
        $completion = new Completion($kind, Term::constant(7), 12, 0);
        $state->handlers = [new Handler($region, $phase)];
        $state->completion = $completion;
        self::assertTrue((new Unwinding($context->program))->resume($state));
        self::assertSame(9, $state->block);
        self::assertSame('normal', $state->completion->kind);
        self::assertNull($state->completion->value);
        self::assertCount(1, $state->handlers);
        self::assertSame('finally', $state->handlers[0]->phase);
        self::assertSame($region, $state->handlers[0]->region);
        self::assertSame($completion, $state->handlers[0]->saved);
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerPendingCompletion(): iterable
    {
        foreach (['try', 'catch'] as $phase) {
            foreach (['return', 'jump', 'normal'] as $kind) {
                yield $phase . ' ' . $kind => [$phase, $kind];
            }
        }
    }

    public function testResumeStopsAtTheJumpDepthAndDoesNotRepeatAnActiveFinally(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $outer = new Handler(new ExceptionRegion([], 20, 21));
        $state->handlers = [$outer, new Handler(new ExceptionRegion([], 30, 31), 'finally')];
        $state->block = 8;
        $state->completion = new Completion('jump', target:15, depth:1);
        self::assertTrue((new Unwinding($context->program))->resume($state));
        self::assertSame(8, $state->previous);
        self::assertSame(15, $state->block);
        self::assertSame('normal', $state->completion->kind);
        self::assertSame([$outer], $state->handlers);
    }

    public function testResumePopsRegionsWithoutFinallyBeforeLeavingTheCallable(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $completion = new Completion('return', Term::constant(7));
        $state->handlers = [new Handler(new ExceptionRegion([], null, 10)), new Handler(new ExceptionRegion([], null, 20))];
        $state->completion = $completion;
        self::assertFalse((new Unwinding($context->program))->resume($state));
        self::assertSame([], $state->handlers);
        self::assertSame($completion, $state->completion);
    }

    public function testRoutesPreservesNonthrowingPathsWhileResumingTheirJumps(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->completion = new Completion('jump', target:7);
        $paths = (new Unwinding($context->program))->routes($state);
        self::assertSame([$state], $paths);
        self::assertSame(7, $state->block);
        self::assertSame('normal', $state->completion->kind);
    }

    public function testThrowRoutesPartitionsOrderedCatchesAndSavesOnlyTheRemainingThrowForFinally(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $exception = Term::parameter('error', 'Throwable');
        $region = new ExceptionRegion([new CatchTarget(['RuntimeException'], '', 4), new CatchTarget(['Error'], '', 5)], 8, 10);
        $state->handlers = [new Handler($region)];
        $state->completion = new Completion('throw', $exception);
        $paths = (new Unwinding($context->program))->throwRoutes($state);
        self::assertCount(3, $paths);
        self::assertSame([4, 5, 8], array_map(static fn (State $path): int => $path->block, $paths));
        self::assertSame(['normal', 'normal', 'normal'], array_map(static fn (State $path): string => $path->completion->kind, $paths));
        self::assertSame(['catch:10:4' => true], $paths[0]->guard);
        self::assertSame(['catch:10:4' => false, 'catch:10:5' => true], $paths[1]->guard);
        self::assertSame(['catch:10:4' => false, 'catch:10:5' => false], $paths[2]->guard);
        self::assertSame('catch', $paths[0]->handlers[0]->phase);
        self::assertSame('catch', $paths[1]->handlers[0]->phase);
        self::assertSame('finally', $paths[2]->handlers[0]->phase);
        self::assertSame('throw', $paths[2]->handlers[0]->saved?->kind);
        self::assertSame([], $paths[0]->locals);
        self::assertSame([], $paths[1]->locals);
        self::assertNotNull($paths[2]->handlers[0]->saved->value);
    }

    public function testThrowRoutesKeepsAnOuterHandlerWhenTheCompletionStopsAtItsDepth(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $outer = new Handler(new ExceptionRegion([new CatchTarget(['Throwable'], 'caught', 10)], 11, 12));
        $state->handlers = [$outer, new Handler(new ExceptionRegion([], null, 20))];
        $state->completion = new Completion('throw', new Term('throwable', 'Error'), depth:1);
        $paths = (new Unwinding($context->program))->throwRoutes($state);
        self::assertSame([$state], $paths);
        self::assertSame([$outer], $state->handlers);
        self::assertSame('throw', $state->completion->kind);
        self::assertSame('Error', $state->completion->value?->literal);
        self::assertSame([], $state->locals);
    }

    public function testCapturePreservesExistingObjectIdentityAndSecretRuntimeErrors(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $unwinding = new Unwinding($context->program);
        $existing = new Term('object', 'exception', attributes:['class' => 'Exception'], secret:true);
        self::assertSame($existing, $unwinding->capture($state, $existing));
        self::assertSame([], $state->memory->cells);
        $state = new State();
        $captured = $unwinding->capture($state, new Term('throwable', 'TypeError', secret:true));
        self::assertTrue($captured->isSecret());
        self::assertIsString($captured->literal);
        self::assertSame('TypeError', $state->memory->classes[$captured->literal]);
        self::assertSame('array', $state->memory->cells['object:' . $captured->literal]->kind);
        self::assertFalse($state->memory->cells['object:' . $captured->literal]->attributes['open']);
    }
}
