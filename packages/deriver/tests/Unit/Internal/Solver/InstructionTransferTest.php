<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\InstructionTransfer;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(InstructionTransfer::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Protocol::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\ProtocolAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Strings::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\TypePredicates::class)]
#[UsesClass(Term::class)]
#[Small]
final class InstructionTransferTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target() { $x="before"; $x="after"; return $x . ":done"; }');
        self::assertSame('after:done', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testOtherConvertsEvalIntoAnExplicitStateBoundary(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $value = (new InstructionTransfer($machine))->other($body, new Instruction('eval', 'symbol-table-boundary', $body->source, 'result', name:'UNSUPPORTED_LANGUAGE_FEATURE'), $state);
        self::assertSame('opaque', $value->kind);
        self::assertSame('opaque', $state->snapshot()['x']->kind);
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testBoundaryKeepsBothCaughtAndNormallyCompletedUnknownCode(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target($code){try{eval($code);}catch(Throwable $e){return "caught";}return "normal";}');
        $values = array_column(array_column(array_column($result->normalOutcomes, 'values'), 'return'), 'literal');
        sort($values);
        self::assertSame(['caught','normal'], $values);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertNotEmpty($result->frontiers);
    }
    public function testStorageRoutesOffsetReadsThroughContainerSemantics(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $state->addresses['base'] = $state->memory->allocate(Term::constant('abc'));
        $state->offsets['slot'] = new \Deriver\Internal\Solver\Offset\Address('base', Term::constant(-1));
        $read = new Instruction('read', 'read', $body->source, 'result', ['slot']);
        $paths = (new InstructionTransfer(new Machine($context)))->storage($body, $read, $state);
        self::assertNotNull($paths);
        self::assertSame('c', $paths[0]->value('result')->native());
    }
    public function testOffsetReadPreservesSilentAbsenceOnComputedArrays(): void
    {
        $context = SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->cells['box'] = Term::array(['value' => Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new Machine($context));
        $state->registers['array'] = Term::array([]);
        $state->registers['key'] = Term::constant('absent');
        $instruction = new Instruction('read', 'array-read', $body->source, 'result', ['array','key'], attributes: ['silent' => true]);
        $paths = (new InstructionTransfer(new Machine($context)))->offsetRead($body, $instruction, $state);
        self::assertSame('uninitialized', $paths[0]->value('result')->kind);
        self::assertSame([], $context->frontiers);
    }
    public function testStorageRetainsUnknownReferenceEffectsAndExceptions(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $state->addresses['unknown'] = new \Deriver\Internal\Memory\Location('unresolved', unknown: true);
        $state->memory->write($state->local('value'), Term::constant(1));
        $reference = new Instruction('ref', 'reference', $body->source, 'result', ['unknown']);
        $paths = (new InstructionTransfer($machine))->storage($body, $reference, $state);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('opaque', $paths[0]->registers['result']->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
    public function testPreparationLeavesOrdinaryOperationsToValueTransfer(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('value', 'constant', $body->source, constant: Term::constant(1));
        self::assertNull((new InstructionTransfer(new Machine($context)))->preparation($body, $instruction, new State()));
    }

    /**
     * @param Term $item Unpack operand
     */
    #[DataProvider('providerInvalidUnpacks')]
    public function testUnpackRejectsNonIterableValues(Term $item, string $exception): void
    {
        $state = new State();
        $state->registers = ['array' => Term::array([]),'item' => $item];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine(SolverFixture::context())))->unpack($instruction, $state);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertNotNull($paths[0]->completion->value);
        self::assertSame($exception, $paths[0]->completion->value->literal);
    }

    /**
     * @return array<string,array{Term,string}>
     */
    public static function providerInvalidUnpacks(): array
    {
        return ['null' => [Term::constant(null),'Error'],'int' => [Term::constant(3),'Error'],'string' => [Term::constant('abc'),'Error'],'bool' => [Term::constant(false),'Error'],'closure' => [new Term('closure', 'body'),'TypeError']];
    }

    public function testUnpackRetainsUnknownIterableEffectsAndBothExits(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->memory->cells['global:x'] = Term::constant(4);
        $state->registers = ['array' => Term::array([]),'item' => Term::parameter('input', 'iterable')];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine($context)))->unpack($instruction, $state);
        self::assertCount(2, $paths);
        self::assertSame('opaque', $paths[0]->registers['result']->kind);
        self::assertSame('opaque', $paths[0]->memory->cells['global:x']->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column(array_values($context->frontiers), 'code'));
    }

    public function testUnpackMergesClosedArraysInInsertionOrder(): void
    {
        $state = new State();
        $state->registers = ['array' => Term::fromNative(['a' => 1,0 => 'first']),'item' => Term::fromNative(['a' => 2,7 => 'last'])];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine(SolverFixture::context())))->unpack($instruction, $state);
        self::assertCount(1, $paths);
        self::assertSame(['a' => 2,0 => 'first',1 => 'last'], $paths[0]->registers['result']->native());
        self::assertSame('normal', $paths[0]->completion->kind);
    }

}
