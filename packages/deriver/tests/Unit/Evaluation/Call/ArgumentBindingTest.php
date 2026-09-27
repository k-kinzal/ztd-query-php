<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ArgumentBinding::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArgumentBindingTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testActualsPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f($a,$b="default",...$rest){return [$a,$b,$rest];} function target(){return f(b:"B",a:"A",extra:3);}');
        self::assertSame(['A', 'B', ['extra' => 3]], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBindMatchesNamedArgumentsBeforeEvaluatingTheCallee(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f($a,$b){return [$a,$b];}function target(){return f(b:2,a:1);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([1,2], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @param bool $readonly Whether the source is a readonly property
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReadonlyActuals')]
    public function testActualsPreservesNamesValuesAndReferencePermissions(bool $readonly): void
    {
        $state = new State();
        $state->registers['value'] = Term::constant('snapshot');
        $state->addresses['address'] = new Location('object:box', ['value']);
        $state->properties['address'] = new PropertySlot(new Term('object', 'box'), 'value', 'Box', new PropertyDeclaration('value', 'Box', readonly:$readonly));
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('value', 'named', location:'address')]);
        $actuals = (new ArgumentBinding(new Machine(SolverFixture::context())))->actuals($instruction, $state);
        self::assertCount(1, $actuals);
        self::assertSame('snapshot', $actuals[0]->value->native());
        self::assertSame('named', $actuals[0]->name);
        self::assertSame($state->addresses['address'], $actuals[0]->location);
        self::assertSame(!$readonly, $actuals[0]->writable);
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function providerReadonlyActuals(): array
    {
        return ['readonly' => [true],'writable' => [false]];
    }

    public function testActualsUnpacksKeysAndReadsReferenceElementsFromCurrentMemory(): void
    {
        $state = new State();
        $cell = $state->memory->allocate(Term::constant('current'));
        $state->registers['array'] = Term::array([4 => Term::constant('first'),'named' => new Term('cell', $cell->root)]);
        $state->addresses['array-address'] = new Location('outer', ['nested']);
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('array', unpack:true, location:'array-address')]);
        $actuals = (new ArgumentBinding(new Machine(SolverFixture::context())))->actuals($instruction, $state);
        self::assertCount(2, $actuals);
        self::assertSame([null,'named'], array_column($actuals, 'name'));
        self::assertSame('first', $actuals[0]->value->native());
        self::assertSame('current', $actuals[1]->value->native());
        self::assertEquals(new Location('outer', ['nested',4]), $actuals[0]->location);
        self::assertEquals(new Location('outer', ['nested','named']), $actuals[1]->location);
        self::assertTrue($actuals[0]->writable);
        self::assertTrue($actuals[1]->writable);
    }

    public function testActualsDoesNotInventAddressesForLiteralArrayUnpacks(): void
    {
        $state = new State();
        $state->registers['array'] = Term::array([Term::constant(1),'x' => Term::constant(2)]);
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('array', unpack:true)]);
        $actuals = (new ArgumentBinding(new Machine(SolverFixture::context())))->actuals($instruction, $state);
        self::assertCount(2, $actuals);
        self::assertSame([null,null], array_column($actuals, 'location'));
        self::assertSame([null,'x'], array_column($actuals, 'name'));
    }

    /**
     * @param Term $value Unknown unpack sequence
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnknownUnpacks')]
    public function testActualsRetainsAnUnknownUnpackAsADependentRemainder(Term $value): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['input'] = $value;
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('input', unpack:true)]);
        $actuals = (new ArgumentBinding(new Machine($context)))->actuals($instruction, $state);
        self::assertCount(1, $actuals);
        self::assertSame('*', $actuals[0]->name);
        self::assertNull($actuals[0]->location);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $actuals[0]->value->literal);
        self::assertSame([$value], $actuals[0]->value->operands);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerUnknownUnpacks(): array
    {
        return ['symbolic' => [Term::parameter('items', 'array')],'open shape' => [Term::array(['known' => Term::constant(1)], true)]];
    }

    public function testBindRetainsCallerConditionsAndSeparatesCaptureModes(): void
    {
        $context = SolverFixture::context('<?php function target($x=3){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $caller = new State();
        $shared = $caller->memory->allocate(Term::constant(1));
        $caller->guard = ['condition' => true];
        $caller->constraints = ['input' => ['min' => 0,'max' => 10,'equal' => null,'excluded' => []]];
        $caller->controls = ['first','second'];
        $caller->observed = true;
        $caller->lateStaticClass = 'Caller';
        $receiver = new Term('object', 'box', attributes:['class' => 'Receiver']);
        $paths = (new ArgumentBinding(new Machine($context)))->bind($callable, $caller, [], $receiver, ['shared' => new Term('cell', $shared->root),'copy' => Term::constant(2)]);
        self::assertCount(1, $paths);
        $entry = $paths[0];
        self::assertSame($caller->guard, $entry->guard);
        self::assertSame($caller->constraints, $entry->constraints);
        self::assertSame($caller->controls, $entry->controls);
        self::assertTrue($entry->observed);
        self::assertSame('Receiver', $entry->lateStaticClass);
        self::assertSame($receiver, $entry->memory->read($entry->local('this')));
        self::assertSame(3, $entry->memory->read($entry->local('x'))->native());
        self::assertSame(2, $entry->memory->read($entry->local('copy'))->native());
        $entry->memory->write($entry->local('shared'), Term::constant(4));
        self::assertSame(4, $entry->memory->read($shared)->native());
        self::assertSame(1, $caller->memory->read($shared)->native());
        self::assertSame([], $caller->locals);
    }

    public function testBindPreservesTheCallingClassWithoutAReceiverAndStopsOnTheFirstError(): void
    {
        $context = SolverFixture::context('<?php function target(int $x,$later=3){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $caller = new State();
        $caller->lateStaticClass = 'Caller';
        $paths = (new ArgumentBinding(new Machine($context)))->bind($callable, $caller, [new PassedArgument(Term::array([]))]);
        self::assertCount(1, $paths);
        self::assertSame('Caller', $paths[0]->lateStaticClass);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
        self::assertSame([], $paths[0]->locals);
        self::assertSame('normal', $caller->completion->kind);
    }

    public function testBindRejectsArgumentOrderBeforeInitializingAnyParameter(): void
    {
        $context = SolverFixture::context('<?php function target($x,$y){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $paths = (new ArgumentBinding(new Machine($context)))->bind($callable, new State(), [new PassedArgument(Term::constant(1), 'x'),new PassedArgument(Term::constant(2))]);
        self::assertCount(1, $paths);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $paths[0]->locals);
    }
}
