<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(MemoryStep::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
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
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
    public function testPrepareStorageResolvesAConcreteDynamicLocal(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers['name'] = Term::constant('sql');
        (new MemoryStep($context))->prepareStorage($body, new Instruction('i', 'dynamic-local', $body->source, 'address', ['name']), $state);
        self::assertSame('sql', $state->addresses['address']->local);
    }

}
