<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use Deriver\Internal\IR\CatchTarget;
use Deriver\Internal\IR\ExceptionRegion;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Control\Handler;
use Deriver\Internal\Solver\Control\Unwinding;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(Unwinding::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
        self::assertSame(0, $state->memory->read(new \Deriver\Internal\Memory\Location('object:'.$object->literal, ['code']))->native());
        self::assertSame('RUNTIME_DIAGNOSTIC', $state->memory->read(new \Deriver\Internal\Memory\Location('object:'.$object->literal, ['message']))->literal);
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
