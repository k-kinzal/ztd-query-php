<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

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
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
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
use Deriver\Result\Frontier;
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
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(MemoryStep::class)]
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
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(Frontier::class)]
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
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class MemoryStepTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEvaluatePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function replace(string &$x) {$x="changed";} function target() {$x="original";replace($x);return $x;}');
        self::assertSame('changed', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testAddressKeepsTheActualVariableBinding(): void
    {
        $state = new State();
        $instruction = new Instruction('local', 'local', new SourceRef('test', 'fixture.php', 0, 1), 'result', name:'x');
        $location = (new MemoryStep(SolverFixture::context()))->address($instruction, $state);
        self::assertSame($state->local('x')->root, $location->root);
        self::assertSame('x', $location->local);
    }
    public function testUninitializedRetainsAWarningForAnUndefinedVariable(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $instruction = new Instruction('read', 'read', new SourceRef('test', 'fixture.php', 0, 1), 'result');
        $value = (new MemoryStep($context))->uninitialized($instruction, $state->local('x'), $state);
        self::assertNull($value->native());
        self::assertSame('PHP_WARNING', array_values($context->frontiers)[0]->code);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBindingUnsetsOnlyOneLocalAlias(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=1;$y=&$x;unset($x);$y=2;return [isset($x),$y];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([false,2], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testIncrementedRetainsNoEffectValuesAndRecordsTargetWarnings(): void
    {
        $context = SolverFixture::context();
        $instruction = new Instruction('increment', 'increment', new SourceRef('s', 'x.php', 0, 1), attributes:['delta' => 1]);
        $value = (new MemoryStep($context))->incremented(Term::constant(true), $instruction);
        self::assertTrue($value->literal);
        self::assertSame('PHP_WARNING', array_values($context->frontiers)[0]->code);
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testPrepareAddressRejectsAnOccupiedMaximumAppendIndexWithoutOverwritingIt(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$a=[9223372036854775807=>1];try{$a[]=2;}catch(Error $e){return $a;}return 999;}');
        self::assertSame([PHP_INT_MAX => 1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    public function testEvaluateBindsScriptLocalsToSharedGlobalStorage(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(3));
        $instruction = new Instruction('local', 'local', $source, 'address', name:'x');
        $value = (new MemoryStep(SolverFixture::context()))->evaluate(new CallableGraph('script:fixture.php', [], [], $source), $instruction, $state);
        self::assertSame('location', $value->kind);
        self::assertSame('global:x', $value->literal);
        self::assertSame('global:x', $state->locals['x']->root);
        self::assertSame(3, $state->memory->cells['global:x']->native());
        self::assertSame('global:x', $state->addresses['address']->root);
    }

    public function testEvaluateWritesAndReadsTheResolvedAddress(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $state->addresses['address'] = $state->memory->allocate(Term::constant(1));
        $value = Term::constant(7, true);
        $state->registers['value'] = $value;
        $step = new MemoryStep(SolverFixture::context());
        self::assertSame($value, $step->evaluate($body, new Instruction('write', 'write', $source, 'result', ['address','value']), $state));
        self::assertSame($value, $step->evaluate($body, new Instruction('read', 'read', $source, 'result', ['address']), $state));
        self::assertSame($value, $state->memory->read($state->addresses['address']));
    }

    public function testEvaluateSilentReadsPreserveAbsenceWithoutWarnings(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $state->addresses['address'] = $state->local('x');
        $step = new MemoryStep($context);
        self::assertSame('uninitialized', $step->evaluate($body, new Instruction('read', 'read-silent', $source, 'result', ['address']), $state)->kind);
        $beforeCount = count($context->frontiers);
        self::assertNull($step->evaluate($body, new Instruction('read', 'read', $source, 'result', ['address']), $state)->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
        self::assertSame(0, $beforeCount);
    }

    public function testEvaluateReferencesAndAliasesShareWrites(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $state->addresses['x'] = $state->local('x');
        $state->addresses['y'] = $state->local('y');
        $state->memory->write($state->addresses['y'], Term::constant(4));
        $step = new MemoryStep(SolverFixture::context());
        self::assertSame(4, $step->evaluate($body, new Instruction('alias', 'alias', $source, 'result', ['x','y']), $state)->native());
        $reference = $step->evaluate($body, new Instruction('reference', 'reference', $source, 'result', ['y']), $state);
        self::assertSame('cell', $reference->kind);
        self::assertSame($state->memory->reference($state->local('x')), $reference->literal);
    }

    #[DataProvider('providerIncrements')]
    public function testEvaluateIncrementKeepsPreAndPostValuesSeparate(int $delta, bool $post, int $expected, int $after): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $state->addresses['x'] = $state->memory->allocate(Term::constant(3));
        $instruction = new Instruction('increment', 'increment', $source, 'result', ['x'], attributes:['delta' => $delta,'post' => $post]);
        $result = (new MemoryStep(SolverFixture::context()))->evaluate(new CallableGraph('target', [], [], $source), $instruction, $state);
        self::assertSame($expected, $result->native());
        self::assertSame($after, $state->memory->read($state->addresses['x'])->native());
    }

    /**
     * @return array<string,array{int,bool,int,int}>
     */
    public static function providerIncrements(): array
    {
        return ['pre increment' => [1,false,4,4],'post increment' => [1,true,3,4],'pre decrement' => [-1,false,2,2],'post decrement' => [-1,true,3,2]];
    }

    public function testEvaluateFailedIncrementCannotWriteTheThrowableIntoStorage(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $value = Term::array([]);
        $state->addresses['x'] = $state->memory->allocate($value);
        $instruction = new Instruction('increment', 'increment', $source, 'result', ['x']);
        $result = (new MemoryStep(SolverFixture::context()))->evaluate(new CallableGraph('target', [], [], $source), $instruction, $state);
        self::assertSame('TypeError', $result->literal);
        self::assertSame($value, $state->memory->read($state->addresses['x']));
    }

    public function testAddressResolvesReturnedCellsAndRejectsValues(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $step = new MemoryStep(SolverFixture::context());
        $state = new State();
        $state->registers = ['cell' => new Term('cell', 'shared'),'value' => Term::constant(1)];
        self::assertSame('shared', $step->address(new Instruction('address', 'returned-address', $source, 'result', ['cell']), $state)->root);
        self::assertTrue($step->address(new Instruction('address', 'returned-address', $source, 'result', ['value']), $state)->unknown);
        self::assertTrue($step->address(new Instruction('address', 'returned-address', $source), $state)->unknown);
    }

    public function testPrepareAddressRetainsOriginalOffsetAndPropertyMetadata(): void
    {
        $state = new State();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $parent = $state->memory->allocate(Term::fromNative(['field' => [1]]));
        $state->addresses['base'] = new Location($parent->root, ['field']);
        $key = Term::constant('0');
        $state->registers['key'] = $key;
        $property = new PropertySlot(new Term('object', 'box'), 'field', 'Box', null);
        $state->properties['base'] = $property;
        $instruction = new Instruction('address', 'element-address', $source, 'result', ['base','key']);
        $step = new MemoryStep(SolverFixture::context());
        $value = $step->prepareAddress($instruction, $state);
        self::assertSame('location', $value->kind);
        self::assertSame($parent->root, $value->literal);
        self::assertSame(['field',0], $state->addresses['result']->path);
        self::assertSame($property, $state->properties['result']);
        self::assertSame($key, $state->offsets['result']->key);
        self::assertSame('base', $state->offsets['result']->parent);
    }

    public function testAddressKeepsUnknownKeysAtTheParentLocation(): void
    {
        $state = new State();
        $state->addresses['base'] = new Location('array', ['field']);
        $state->registers['key'] = Term::parameter('key', 'string');
        $instruction = new Instruction('address', 'element-address', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['base','key']);
        $address = (new MemoryStep(SolverFixture::context()))->address($instruction, $state);
        self::assertSame('array', $address->root);
        self::assertSame(['field'], $address->path);
        self::assertTrue($address->unknown);
    }

    public function testPrepareAddressAppendsUsingTheStoredCounter(): void
    {
        $state = new State();
        $state->addresses['base'] = $state->memory->allocate(Term::fromNative([4 => 'a']));
        $instruction = new Instruction('address', 'element-address', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['base','']);
        (new MemoryStep(SolverFixture::context()))->prepareAddress($instruction, $state);
        self::assertSame([5], $state->addresses['result']->path);
        self::assertNull($state->offsets['result']->key);
    }

    public function testUninitializedTypedPropertiesAndThisProduceErrorsWithoutWarnings(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->memory->cells['typed'] = new Term('uninitialized', attributes:['type' => 'int']);
        $instruction = new Instruction('read', 'read', new SourceRef('test', 'fixture.php', 0, 1));
        $step = new MemoryStep($context);
        self::assertSame('Error', $step->uninitialized($instruction, new Location('typed'), $state)->literal);
        self::assertSame('Error', $step->uninitialized($instruction, new Location('missing', local:'this'), $state)->literal);
        self::assertSame([], $context->frontiers);
    }

    public function testBindingUsesExistingGlobalAndStaticStorageWithoutReinitializing(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $state->memory->cells = ['global:x' => Term::constant(7),'static:target:y' => Term::constant(8)];
        $state->registers['initializer'] = Term::constant(99);
        $step = new MemoryStep(SolverFixture::context());
        self::assertNull($step->binding($body, new Instruction('global', 'global', $source), $state, new Location('local-x', local:'x'))->native());
        self::assertNull($step->binding($body, new Instruction('static', 'static-local', $source, operands:['address','initializer']), $state, new Location('local-y', local:'y'))->native());
        self::assertSame(7, $state->memory->read($state->local('x'))->native());
        self::assertSame(8, $state->memory->read($state->local('y'))->native());
        self::assertSame('global:x', $state->local('x')->root);
        self::assertSame('static:target:y', $state->local('y')->root);
    }

    public function testBindingInitializesUnseenStaticsAndKeepsUnknownSharedState(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $location = $state->local('x');
        $step = new MemoryStep(SolverFixture::context());
        $check = new Instruction('check', 'static-initialized', $source);
        self::assertFalse($step->binding($body, $check, $state, $location)->native());
        $state->registers['initializer'] = Term::constant(4);
        $step->binding($body, new Instruction('static', 'static-local', $source, operands:['address','initializer']), $state, $location);
        self::assertTrue($step->binding($body, $check, $state, $location)->native());
        self::assertSame(4, $state->memory->read($state->local('x'))->native());
        $state->memory->unknownShared = 'UNKNOWN_CALL';
        self::assertTrue($step->binding($body, $check, $state, new Location('unseen', local:'y'))->native());
        self::assertSame('UNKNOWN_CALL', $state->memory->read($state->local('y'))->literal);
    }

    public function testBindingCreatesExternalGlobalsAndRemovesOnlySelectedArrayElements(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $step = new MemoryStep(SolverFixture::context());
        $step->binding($body, new Instruction('global', 'global', $source), $state, new Location('unseen', local:'x'));
        self::assertSame('external', $state->memory->read($state->local('x'))->kind);
        self::assertSame('global:x', $state->memory->read($state->local('x'))->literal);
        $array = $state->memory->allocate(Term::fromNative(['a' => 1,'b' => 2]));
        $step->binding($body, new Instruction('unset', 'unset', $source), $state, new Location($array->root, ['a'], local:'array'));
        self::assertSame(['b' => 2], $state->memory->read($array)->native());
    }

    #[DataProvider('providerTemporaryReferenceDiagnostics')]
    public function testReturnedAddressAnchorsTemporaryValuesAndReportsOnlyRequiredReferenceNotices(bool $diagnostic): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $value = Term::fromNative([7]);
        $state->registers['returned'] = $value;
        $source = new SourceRef('test', 'a.php', 1, 9);
        $instruction = new Instruction('i', 'returned-address', $source, 'address', ['returned'], attributes:['temporary-reference' => true, 'temporary-warning' => $diagnostic]);
        $address = (new MemoryStep($context))->returnedAddress($instruction, $state);
        self::assertFalse($address->unknown);
        self::assertSame($value, $state->memory->read($address));
        self::assertSame($diagnostic ? ['PHP_WARNING'] : [], array_column($context->frontiers, 'code'));
        self::assertSame($diagnostic ? [$source] : [], array_column($context->frontiers, 'at'));
        $state->memory->write($address, Term::constant(8));
        self::assertSame($value, $state->registers['returned']);
    }

    /**
     * @return iterable<string,array{bool}>
     */
    public static function providerTemporaryReferenceDiagnostics(): iterable
    {
        yield 'assignment from ordinary return' => [true];
        yield 'foreach temporary' => [false];
    }

    public function testReturnedAddressRetainsAnExistingReturnedCellWithoutAReferenceNotice(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $original = $state->memory->allocate(Term::fromNative([7]));
        $state->registers['returned'] = new Term('cell', $original->root);
        $instruction = new Instruction('i', 'returned-address', new SourceRef('test', 'a.php', 1, 9), 'address', ['returned'], attributes:['temporary-reference' => true]);
        $address = (new MemoryStep($context))->returnedAddress($instruction, $state);
        self::assertSame($original->root, $address->root);
        self::assertFalse($address->unknown);
        self::assertSame([], $context->frontiers);
        $state->memory->write($address, Term::constant(8));
        self::assertSame(8, $state->memory->read($original)->literal);
    }
}
