<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Solver\Transfer\MemoryStep;
use Deriver\Internal\Solver\Transfer\PropertySlot;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(MemoryStep::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
        $value = (new MemoryStep(SolverFixture::context()))->evaluate(new CallableIR('script:fixture.php', [], [], $source), $instruction, $state);
        self::assertSame('location', $value->kind);
        self::assertSame('global:x', $value->literal);
        self::assertSame('global:x', $state->locals['x']->root);
        self::assertSame(3, $state->memory->cells['global:x']->native());
        self::assertSame('global:x', $state->addresses['address']->root);
    }

    public function testEvaluateWritesAndReadsTheResolvedAddress(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableIR('target', [], [], $source);
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
        $body = new CallableIR('target', [], [], $source);
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
        $body = new CallableIR('target', [], [], $source);
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
        $result = (new MemoryStep(SolverFixture::context()))->evaluate(new CallableIR('target', [], [], $source), $instruction, $state);
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
        $result = (new MemoryStep(SolverFixture::context()))->evaluate(new CallableIR('target', [], [], $source), $instruction, $state);
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
        $body = new CallableIR('target', [], [], $source);
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
        $body = new CallableIR('target', [], [], $source);
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
        $body = new CallableIR('target', [], [], $source);
        $state = new State();
        $step = new MemoryStep(SolverFixture::context());
        $step->binding($body, new Instruction('global', 'global', $source), $state, new Location('unseen', local:'x'));
        self::assertSame('external', $state->memory->read($state->local('x'))->kind);
        self::assertSame('global:x', $state->memory->read($state->local('x'))->literal);
        $array = $state->memory->allocate(Term::fromNative(['a' => 1,'b' => 2]));
        $step->binding($body, new Instruction('unset', 'unset', $source), $state, new Location($array->root, ['a'], local:'array'));
        self::assertSame(['b' => 2], $state->memory->read($array)->native());
    }
}
