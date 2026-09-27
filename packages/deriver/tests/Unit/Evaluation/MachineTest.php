<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\ResidualPaths;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Derivation;
use Deriver\Result\Exceptional;
use Deriver\Result\Frontier;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
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
use Deriver\Value\Identity;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;
use Tests\Fake\SummaryFixture;

/**
 * @covers \Deriver\Evaluation\Machine
 */
#[CoversClass(Machine::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
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
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(ResidualPaths::class)]
#[UsesClass(Resources::class)]
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
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
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
#[UsesClass(Identity::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
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

        $context->demands[$body] = (new Discovery($context))->instructions($body);
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
        $context->demands[$body] = (new Discovery($context))->instructions($body);
        $paths = (new Machine($context))->execute($body, new State());
        self::assertSame(1, $paths[0]->completion->value?->literal);
        self::assertSame([], $context->normal);
    }
    public function testRunStopsLongDistinctCallChainsBeforeTheHostStackFails(): void
    {
        $source = '<?php function target(){return a();}function a(){return b();}function b(){return c();}function c(){return d();}function d(){return e();}function e(){return f();}function f(){return 1;}';
        $context = SolverFixture::context($source, configuration:new Configuration(resources:new ResourceLimits(stackFrames:64)));
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
        $body = new CallableGraph('target', [], [], $source, $type, strict:$strict);
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
        $body = new CallableGraph('target', [], [], $source, 'never');
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
        $body = new CallableGraph('target', [], [], $source, 'int', byReference:true);
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
        $body = new CallableGraph('target', [], [], $source, 'int', byReference:true);
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
        $body = new CallableGraph('Base::create', [], [], $source, 'static', className:'Base');
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
        $body = new CallableGraph('target', [], [], $source, byReference:$byReference);
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
        $body = new CallableGraph('target', [], [new BasicBlock(0, [$irrelevant,$needed], new Terminator('return', 'result'))], $source);
        $context->demands[$body] = ['needed' => true];
        $paths = (new Machine($context))->block($body, new State());
        self::assertSame(19, $paths[0]->completion->value?->native());
        self::assertArrayNotHasKey('unused', $paths[0]->registers);
        self::assertSame(['needed'], $paths[0]->evidence);
    }

    public function testBlockPreservesNormalAndExceptionalResidualsWhenWorkIsExhausted(): void
    {
        $context = SolverFixture::context(budget:new Budget(transfers:1));
        $source = SummaryFixture::body($context)->source;
        $first = new Instruction('first', 'constant', $source, 'one', constant:Term::constant(1));
        $last = new Instruction('last', 'constant', $source, 'two', constant:Term::constant(2));
        $body = new CallableGraph('target', [], [new BasicBlock(0, [$first,$last], new Terminator('return', 'two'))], $source);
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
        $context = SolverFixture::context(budget:new Budget(recursion:2));
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
        $context = SolverFixture::context(budget:new Budget(recursion:2));
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
