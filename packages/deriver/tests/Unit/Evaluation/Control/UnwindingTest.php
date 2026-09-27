<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(Unwinding::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
