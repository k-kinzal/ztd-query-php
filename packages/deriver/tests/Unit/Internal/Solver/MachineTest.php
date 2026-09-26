<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use Deriver\Internal\IR\BasicBlock;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\ExceptionRegion;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\IR\Terminator;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Control\Handler;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;
use Tests\Fake\SummaryFixture;

/**
 * @covers \Deriver\Internal\Solver\Machine
 */
#[CoversClass(Machine::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ResidualPaths::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(Term::class)]
#[Small]
final class MachineTest extends TestCase
{
    public function testRunReturnsTheObservedValue(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $states = $machine->run($body, new State());
        self::assertCount(1, $states);
        self::assertSame('return', $states[0]->completion->kind);
        self::assertSame(1, $states[0]->completion->value?->native());
    }
    public function testReturnedEnforcesTheDeclaredReturnType(): void
    {
        $context = SolverFixture::context('<?php declare(strict_types=1);function target():int{}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->completion = new Completion('return', Term::constant('invalid'));
        $paths = (new Machine($context))->returned($body, $state);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }
    public function testBlockTransfersOnlyDemandedDefinitions(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $paths = $machine->block($body, new State());
        self::assertSame(1, $paths[0]->completion->value?->native());
    }
    public function testStepRecordsAConstantDefinition(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $instruction = $body->blocks[0]->instructions[0];
        $paths = $machine->step($body, $instruction, new State());
        self::assertSame(1, $paths[0]->value($instruction->result)->native());
        self::assertArrayHasKey($instruction->id, $context->evidence);
    }
    public function testTerminateUsesTheAlreadyEvaluatedReturnRegister(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $state = new State();
        $state->registers['result'] = Term::constant(3);
        $paths = $machine->terminate($body, new Terminator('return', 'result'), $state);
        self::assertSame(3, $paths[0]->completion->value?->native());
    }
    public function testBranchEliminatesAContradictedConstantPath(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $state = new State();
        $state->registers['condition'] = Term::constant(false);
        $paths = $machine->branch(new Terminator('branch', 'condition', [1,2]), $state);
        self::assertCount(1, $paths);
        self::assertSame(2, $paths[0]->block);
    }
    public function testExecuteTransfersOnceWithoutPublishingSpeculativeObservations(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $context->demands[$body] = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        $paths = (new Machine($context))->execute($body, new State());
        self::assertSame(1, $paths[0]->completion->value?->literal);
        self::assertSame([], $context->normal);
    }
    public function testRunStopsLongDistinctCallChainsBeforeTheHostStackFails(): void
    {
        $source = '<?php function target(){return a();}function a(){return b();}function b(){return c();}function c(){return d();}function d(){return e();}function e(){return f();}function f(){return 1;}';
        $context = SolverFixture::context($source, configuration:new \Deriver\Api\Project\Configuration(resources:new \Deriver\Api\Execution\ResourceLimits(stackFrames:64)));
        $body = SummaryFixture::body($context);
        $paths = (new Machine($context))->run($body, new State());
        self::assertSame('STACK_LIMIT', $context->stopReason);
        self::assertNotEmpty($paths);
        self::assertContains('opaque', array_map(static fn (State $path): ?string => $path->completion->value?->kind, $paths));
        self::assertContains('throwable', array_map(static fn (State $path): ?string => $path->completion->value?->kind, $paths));
    }

    /**
     * @param string $type Declared return type
     * @param bool $strict Calling-file mode
     * @param Term|null $value Returned value or implicit null
     * @param string $completion Expected completion kind
     * @param mixed $expected Expected scalar or exception name
     */
    #[DataProvider('providerReturnTypes')]
    public function testReturnedValidatesFinalValuesUnderTheDeclaringFileMode(string $type, bool $strict, ?Term $value, string $completion, mixed $expected): void
    {
        $context = SolverFixture::context();
        $source = SummaryFixture::body($context)->source;
        $body = new CallableIR('target', [], [], $source, $type, strict:$strict);
        $state = new State();
        $state->completion = new Completion('return', $value);
        $paths = (new Machine($context))->returned($body, $state);
        self::assertCount(1, $paths);
        self::assertSame($completion, $paths[0]->completion->kind);
        self::assertSame($expected, $paths[0]->completion->value?->literal);
    }

    /**
     * @return iterable<string,array{string,bool,Term|null,string,mixed}>
     */
    public static function providerReturnTypes(): iterable
    {
        yield 'weak integer' => ['int',false,Term::constant('12'),'return',12];
        yield 'strict integer' => ['int',true,Term::constant('12'),'throw','TypeError'];
        yield 'exact integer' => ['int',true,Term::constant(12),'return',12];
        yield 'integer to float' => ['float',true,Term::constant(12),'return',12.0];
        yield 'null accepted' => ['int|null',true,null,'return',null];
        yield 'null rejected' => ['int',false,null,'throw','TypeError'];
        yield 'void' => ['void',true,null,'return',null];
        yield 'mixed' => ['mixed',true,Term::constant('abc'),'return','abc'];
        yield 'never' => ['never',false,null,'throw','TypeError'];
        yield 'weak boolean' => ['bool',false,Term::constant(''),'return',false];
        yield 'strict false' => ['false',true,Term::constant(false),'return',false];
    }

    public function testReturnedKeepsExceptionsOutsideReturnTypeValidation(): void
    {
        $context = SolverFixture::context();
        $source = SummaryFixture::body($context)->source;
        $body = new CallableIR('target', [], [], $source, 'never');
        $state = new State();
        $completion = new Completion('throw', new Term('throwable', 'RuntimeException'));
        $state->completion = $completion;
        self::assertSame([$state], (new Machine($context))->returned($body, $state));
        self::assertSame($completion, $state->completion);
    }

    public function testReturnedCoercesSharedCellsAndRetainsReferenceIdentity(): void
    {
        $context = SolverFixture::context();
        $source = SummaryFixture::body($context)->source;
        $body = new CallableIR('target', [], [], $source, 'int', byReference:true);
        $state = new State();
        $cell = $state->memory->allocate(Term::constant('12'));
        $reference = new Term('cell', $cell->root);
        $state->completion = new Completion('return', $reference);
        $paths = (new Machine($context))->returned($body, $state);
        self::assertCount(1, $paths);
        self::assertSame('return', $paths[0]->completion->kind);
        self::assertSame($reference, $paths[0]->completion->value);
        self::assertSame(12, $paths[0]->memory->read($cell)->native());
    }

    public function testReturnedKeepsThePreCoercionCellOnAnUncertainTypeError(): void
    {
        $context = SolverFixture::context();
        $source = SummaryFixture::body($context)->source;
        $body = new CallableIR('target', [], [], $source, 'int', byReference:true);
        $state = new State();
        $input = Term::parameter('input');
        $cell = $state->memory->allocate($input);
        $reference = new Term('cell', $cell->root);
        $state->completion = new Completion('return', $reference);
        $paths = (new Machine($context))->returned($body, $state);
        self::assertCount(2, $paths);
        self::assertSame('return', $paths[0]->completion->kind);
        self::assertSame($reference, $paths[0]->completion->value);
        self::assertSame('type-refinement', $paths[0]->memory->read($cell)->kind);
        self::assertSame('int', $paths[0]->memory->read($cell)->attributes['type']);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
        self::assertSame($input, $paths[1]->memory->read($cell));
    }

    public function testReturnedResolvesLateStaticTypesAgainstTheBoundReceiverClass(): void
    {
        $context = SolverFixture::context('<?php class Base{}class Child extends Base{}function target(){}');
        $source = SummaryFixture::body($context)->source;
        $body = new CallableIR('Base::create', [], [], $source, 'static', className:'Base');
        $state = new State();
        $value = new Term('object', 'instance', attributes:['class' => 'Child']);
        $state->lateStaticClass = 'Child';
        $state->completion = new Completion('return', $value);
        $paths = (new Machine($context))->returned($body, $state);
        self::assertCount(1, $paths);
        self::assertSame('return', $paths[0]->completion->kind);
        self::assertSame($value, $paths[0]->completion->value);
    }

    public function testTerminateJumpRecordsThePredecessorAndUsesTheFirstTarget(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->block = 7;
        $state->previous = 3;
        $paths = (new Machine($context))->terminate($body, new Terminator('jump', targets:[12,99]), $state);
        self::assertSame([$state], $paths);
        self::assertSame(7, $paths[0]->previous);
        self::assertSame(12, $paths[0]->block);
        self::assertSame('normal', $paths[0]->completion->kind);
    }

    public function testTerminateLeaveTryKeepsOuterHandlersAndResumesAtTheRegionContinuation(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->block = 7;
        $outer = new Handler(new ExceptionRegion([], 40, 50));
        $state->handlers = [$outer,new Handler(new ExceptionRegion([], null, 12))];
        $paths = (new Machine($context))->terminate($body, new Terminator('leave-try'), $state);
        self::assertSame([$state], $paths);
        self::assertSame([$outer], $paths[0]->handlers);
        self::assertSame(12, $paths[0]->block);
        self::assertSame(7, $paths[0]->previous);
        self::assertSame('normal', $paths[0]->completion->kind);
    }

    public function testTerminateLeaveTryRunsFinallyBeforeContinuing(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->handlers = [new Handler(new ExceptionRegion([], 8, 12))];
        $paths = (new Machine($context))->terminate($body, new Terminator('leave-try'), $state);
        self::assertCount(1, $paths);
        self::assertSame(8, $paths[0]->block);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('finally', $paths[0]->handlers[0]->phase);
        self::assertSame('jump', $paths[0]->handlers[0]->saved?->kind);
        self::assertSame(12, $paths[0]->handlers[0]->saved->target);
        self::assertSame(0, $paths[0]->handlers[0]->saved->depth);
    }

    public function testTerminateResumeRestoresTheSavedCompletionAndRemovesItsHandler(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $saved = new Completion('return', Term::constant(19));
        $state->handlers = [new Handler(new ExceptionRegion([], 8, 12), 'finally', $saved)];
        $paths = (new Machine($context))->terminate($body, new Terminator('resume'), $state);
        self::assertCount(1, $paths);
        self::assertSame($saved, $paths[0]->completion);
        self::assertSame([], $paths[0]->handlers);
    }

    public function testTerminateResumeWithoutASavedCompletionReturnsNull(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $paths = (new Machine($context))->terminate($body, new Terminator('resume'), $state);
        self::assertCount(1, $paths);
        self::assertSame('return', $paths[0]->completion->kind);
        self::assertNull($paths[0]->completion->value?->native());
    }

    public function testTerminateCompleteJumpUnwindsOnlyTheExitedRegion(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $outer = new Handler(new ExceptionRegion([], 40, 50));
        $state->handlers = [$outer,new Handler(new ExceptionRegion([], 8, 12))];
        $paths = (new Machine($context))->terminate($body, new Terminator('complete-jump', targets:[29], handlerDepth:1), $state);
        self::assertCount(1, $paths);
        self::assertSame(8, $paths[0]->block);
        self::assertSame($outer, $paths[0]->handlers[0]);
        self::assertSame(29, $paths[0]->handlers[1]->saved?->target);
        self::assertSame(1, $paths[0]->handlers[1]->saved->depth);
    }

    /**
     * @param bool $byReference Return reference mode
     * @param string $kind Expected value category
     * @param mixed $literal Expected value payload
     */
    #[DataProvider('providerReturnReferences')]
    public function testTerminateDistinguishesReferenceAndValueReturns(bool $byReference, string $kind, mixed $literal): void
    {
        $context = SolverFixture::context();
        $source = SummaryFixture::body($context)->source;
        $body = new CallableIR('target', [], [], $source, byReference:$byReference);
        $state = new State();
        $state->memory->cells['shared'] = Term::constant(23);
        $state->registers['value'] = new Term('cell', 'shared');
        $paths = (new Machine($context))->terminate($body, new Terminator('return', 'value'), $state);
        self::assertCount(1, $paths);
        self::assertSame('return', $paths[0]->completion->kind);
        self::assertSame($kind, $paths[0]->completion->value?->kind);
        self::assertSame($literal, $paths[0]->completion->value->literal);
    }

    /**
     * @return iterable<string,array{bool,string,mixed}>
     */
    public static function providerReturnReferences(): iterable
    {
        yield 'reference' => [true,'cell','shared'];
        yield 'value' => [false,'constant',23];
    }

    public function testBranchForksMemoryAndRetainsUniqueControllingEvidence(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->block = 7;
        $state->registers['condition'] = Term::parameter('condition', 'bool');
        $state->producers['condition'] = 'predicate';
        $state->controls = ['outer','predicate'];
        $state->memory->write($state->local('value'), Term::constant(4));
        $paths = (new Machine($context))->branch(new Terminator('branch', 'condition', [12,19]), $state);
        self::assertCount(2, $paths);
        self::assertSame([12,19], array_column($paths, 'block'));
        self::assertSame([7,7], array_column($paths, 'previous'));
        self::assertSame(['outer','predicate'], $paths[0]->controls);
        self::assertSame(['outer','predicate'], $paths[1]->controls);
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        self::assertNull($paths[0]->stableHeader);
        self::assertNull($paths[1]->stableHeader);
        $paths[0]->memory->write($paths[0]->local('value'), Term::constant(8));
        self::assertSame(4, $paths[1]->snapshot()['value']->native());
        self::assertSame(4, $state->snapshot()['value']->native());
    }

    public function testBranchAtAStableLoopHeaderKeepsOnlyTheExitPath(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->block = 7;
        $state->stableHeader = 7;
        $state->registers['condition'] = Term::parameter('condition', 'bool');
        $paths = (new Machine($context))->branch(new Terminator('branch', 'condition', [12,19]), $state);
        self::assertCount(1, $paths);
        self::assertSame(19, $paths[0]->block);
        self::assertNull($paths[0]->stableHeader);
    }

    public function testBlockSkipsUndemandedInstructions(): void
    {
        $context = SolverFixture::context();
        $source = SummaryFixture::body($context)->source;
        $needed = new Instruction('needed', 'constant', $source, 'result', constant:Term::constant(19));
        $irrelevant = new Instruction('irrelevant', 'constant', $source, 'unused', constant:Term::constant(7));
        $body = new CallableIR('target', [], [new BasicBlock(0, [$irrelevant,$needed], new Terminator('return', 'result'))], $source);
        $context->demands[$body] = ['needed' => true];
        $paths = (new Machine($context))->block($body, new State());
        self::assertSame(19, $paths[0]->completion->value?->native());
        self::assertArrayNotHasKey('unused', $paths[0]->registers);
        self::assertSame(['needed'], $paths[0]->evidence);
    }

    public function testBlockPreservesNormalAndExceptionalResidualsWhenWorkIsExhausted(): void
    {
        $context = SolverFixture::context(budget:new \Deriver\Api\Query\Budget(transfers:1));
        $source = SummaryFixture::body($context)->source;
        $first = new Instruction('first', 'constant', $source, 'one', constant:Term::constant(1));
        $last = new Instruction('last', 'constant', $source, 'two', constant:Term::constant(2));
        $body = new CallableIR('target', [], [new BasicBlock(0, [$first,$last], new Terminator('return', 'two'))], $source);
        $context->demands[$body] = ['first' => true,'last' => true];
        $paths = (new Machine($context))->block($body, new State());
        self::assertCount(2, $paths);
        self::assertSame(['return','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame('BUDGET_EXCEEDED', $paths[0]->completion->value?->literal);
        self::assertSame('Throwable', $paths[1]->completion->value?->literal);
        self::assertArrayNotHasKey('two', $paths[0]->registers);
        self::assertContains('logical-work', array_column($context->frontiers, 'operation'));
    }

    public function testStepTurnsThrowableResultsIntoExceptionalCompletionsAndRecordsEvidence(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $value = new Term('throwable', 'TypeError');
        $instruction = new Instruction('failure', 'constant', $body->source, 'result', constant:$value);
        $paths = (new Machine($context))->step($body, $instruction, new State());
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame($value, $paths[0]->completion->value);
        self::assertSame(['failure'], $paths[0]->evidence);
        self::assertArrayHasKey('failure', $context->evidence);
    }

    public function testExecuteDoesNotReexecuteAnAlreadyCompletedEntry(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $value = Term::constant(19);
        $state->completion = new Completion('return', $value);
        $paths = (new Machine($context))->execute($body, $state);
        self::assertSame([$state], $paths);
        self::assertSame($value, $paths[0]->completion->value);
        self::assertSame([], $paths[0]->registers);
        self::assertSame([], $paths[0]->evidence);
    }

    public function testRunRestoresActiveDepthAndKeepsTheLastPermittedRecursiveEntry(): void
    {
        $context = SolverFixture::context(budget:new \Deriver\Api\Query\Budget(recursion:2));
        $body = SummaryFixture::body($context);
        $context->active['target'] = 1;
        $paths = (new Machine($context))->run($body, new State());
        self::assertCount(1, $paths);
        self::assertSame(1, $paths[0]->completion->value?->native());
        self::assertSame(1, $context->active['target']);
        self::assertSame(['target' => true], $context->graphs);
    }

    public function testRunRetainsBothResidualExitsAfterTheRecursionLimit(): void
    {
        $context = SolverFixture::context(budget:new \Deriver\Api\Query\Budget(recursion:2));
        $body = SummaryFixture::body($context);
        $context->active['target'] = 2;
        $paths = (new Machine($context))->run($body, new State());
        self::assertCount(2, $paths);
        self::assertSame(['return','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame('BUDGET_EXCEEDED', $paths[0]->completion->value?->literal);
        self::assertSame(2, $context->active['target']);
        self::assertSame([], $context->graphs);
        self::assertContains('recursive-specialization', array_column($context->frontiers, 'operation'));
    }
}
