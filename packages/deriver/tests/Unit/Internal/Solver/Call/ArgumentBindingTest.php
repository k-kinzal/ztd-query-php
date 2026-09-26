<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\Argument;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\IR\PropertyDeclaration;
use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\Call\ArgumentBinding;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Solver\Transfer\PropertySlot;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ArgumentBinding::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
