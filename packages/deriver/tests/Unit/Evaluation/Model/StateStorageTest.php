<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Model;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Evaluation\Model\StateStorage
 */
#[CoversClass(StateStorage::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
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
#[UsesClass(\Deriver\Value\Lattice::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class StateStorageTest extends TestCase
{
    public function testAddressDoesNotExposeAnOrdinaryProperty(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots: [new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $storage = new StateStorage($context);

        $storage->initialize($state, 'object');
        $state->registers['receiver'] = new Term('object', 'object');
        $instruction = new Instruction('slot', 'model-state-address', new SourceRef('test', 'model:test', 0, 1), 'address', ['receiver'], 'example.slot');
        $storage->address($instruction, $state);
        self::assertSame('model:object', $state->addresses['address']->root);
        self::assertSame([], $state->properties);
        self::assertSame(1, $state->memory->read($state->addresses['address'])->native());
    }
    public function testInitializeDoesNotAssumeAnExternalReceiverHasItsNewObjectDefaults(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots: [new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $storage = new StateStorage($context);

        $storage->initialize($state, 'external', external: true);
        self::assertSame('state-input', $state->memory->read(new Location('model:external', ['example.slot']))->kind);
        self::assertSame('int', $state->memory->propertyTypes['model:external']['example.slot']);
    }
    public function testInitializeCopiesTheCurrentValueOnClone(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots: [new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $storage = new StateStorage($context);

        $storage->initialize($state, 'a');
        $state->memory->write(new Location('model:a', ['example.slot']), Term::constant(4));
        $storage->initialize($state, 'b', 'a');
        self::assertSame(4, $state->memory->read(new Location('model:b', ['example.slot']))->native());
    }
    public function testObserveSelectsTheRegisteredAbstractRecord(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots: [new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $storage = new StateStorage($context);
        $storage->initialize($state, 'a');
        self::assertSame(1, $storage->observe(new Term('object', 'a'), 'example.slot', $state)->native());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidAddress')]
    public function testAddressRejectsInvalidReceiversAndUnknownSlots(Term $receiver, string $slot): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots:[new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $state->registers['receiver'] = $receiver;
        $source = new SourceRef('test', 'model:test', 3, 8);
        $result = (new StateStorage($context))->address(new Instruction('i', 'model-state-address', $source, 'address', ['receiver'], $slot), $state);
        self::assertSame('opaque', $result->kind);
        self::assertSame('MODEL_CONTRACT_VIOLATION', $result->literal);
        self::assertTrue($state->addresses['address']->unknown);
        self::assertSame([], $state->memory->cells);
        self::assertCount(1, $context->frontiers);
        self::assertSame($source, array_values($context->frontiers)[0]->at);
        self::assertSame('model-state-address', array_values($context->frontiers)[0]->operation);
    }

    /**
     * @return iterable<string,array{Term,string}>
     */
    public static function providerInvalidAddress(): iterable
    {
        yield 'unregistered slot' => [new Term('object', 'box'), 'example.missing'];
        yield 'scalar masquerading as identity' => [Term::constant('box'), 'example.slot'];
        yield 'array' => [Term::array([]), 'example.slot'];
        yield 'missing object identity' => [new Term('object'), 'example.slot'];
        yield 'numeric external identity' => [new Term('external', 12), 'example.slot'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerStateReceivers')]
    public function testAddressInitializesAcceptedExternalIdentitiesWithSymbolicState(string $kind): void
    {
        $slot = new StateSlot('example.slot', 'int', Term::constant(1));
        $context = SolverFixture::context(configuration: new Configuration(stateSlots:[$slot]));
        $state = new State();
        $state->registers['receiver'] = new Term($kind, 'box');
        $instruction = new Instruction('i', 'model-state-address', new SourceRef('test', 'model:test', 3, 8), 'address', ['receiver'], 'example.slot');
        $storage = new StateStorage($context);
        $result = $storage->address($instruction, $state);
        self::assertSame('location', $result->kind);
        self::assertSame('model:box', $result->literal);
        self::assertSame(['example.slot'], $state->addresses['address']->path);
        self::assertFalse($state->addresses['address']->unknown);
        $value = $state->memory->read($state->addresses['address']);
        self::assertSame('state-input', $value->kind);
        self::assertSame('box:example.slot', $value->literal);
        self::assertSame('int', $value->attributes['type']);
        self::assertSame($slot, $state->memory->slotContracts['example.slot']);
        self::assertSame('int', $state->memory->propertyTypes['model:box']['example.slot']);
        $state->memory->write($state->addresses['address'], Term::constant(9));
        $storage->address($instruction, $state);
        self::assertSame(9, $state->memory->read($state->addresses['address'])->literal);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerStateReceivers(): iterable
    {
        yield 'allocated object' => ['object'];
        yield 'symbolic parameter' => ['parameter'];
        yield 'external object' => ['external'];
    }

    public function testInitializeAppliesDistinctCloneRulesAndPreservesCopiedReferences(): void
    {
        $copy = new StateSlot('example.copy', 'int', Term::constant(1));
        $reset = new StateSlot('example.reset', 'int', Term::constant(2), clone:'reset');
        $symbolic = new StateSlot('example.input', 'string', clone:'reset');
        $context = SolverFixture::context(configuration: new Configuration(stateSlots:[$copy, $reset, $symbolic]));
        $state = new State();
        $storage = new StateStorage($context);
        $storage->initialize($state, 'original');
        $cell = $state->memory->allocate(Term::constant(7));
        $state->memory->write(new Location('model:original', ['example.copy']), new Term('cell', $cell->root));
        $state->memory->write(new Location('model:original', ['example.reset']), Term::constant(8));
        $storage->initialize($state, 'clone', 'original');
        self::assertSame(7, $state->memory->read(new Location('model:clone', ['example.copy']))->literal);
        self::assertSame(2, $state->memory->read(new Location('model:clone', ['example.reset']))->literal);
        $input = $state->memory->read(new Location('model:clone', ['example.input']));
        self::assertSame('state-input', $input->kind);
        self::assertSame('clone:example.input', $input->literal);
        self::assertSame('string', $input->attributes['type']);
        $state->memory->write($cell, Term::constant(12));
        self::assertSame(12, $state->memory->read(new Location('model:original', ['example.copy']))->literal);
        self::assertSame(12, $state->memory->read(new Location('model:clone', ['example.copy']))->literal);
        self::assertSame(8, $state->memory->read(new Location('model:original', ['example.reset']))->literal);
    }

    public function testInitializeCloningAnUnobservedSourceRetainsItsStateIdentity(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots:[new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        (new StateStorage($context))->initialize($state, 'clone', 'external');
        $value = $state->memory->read(new Location('model:clone', ['example.slot']));
        self::assertSame('state-input', $value->kind);
        self::assertSame('external:example.slot', $value->literal);
        self::assertSame('int', $value->attributes['type']);
        self::assertArrayNotHasKey('model:external', $state->memory->cells);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidObservation')]
    public function testObserveRetainsInvalidReceiverDependenciesAndOptionalProvenance(Term $receiver, ?SourceRef $source): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $result = (new StateStorage($context))->observe($receiver, 'example.slot', $state, $source);
        self::assertSame('opaque', $result->kind);
        self::assertSame($source === null ? 'UNKNOWN_STATE_RECEIVER' : 'UNSUPPORTED_MODEL_CASE', $result->literal);
        self::assertSame([$receiver], $result->operands);
        self::assertCount($source === null ? 0 : 1, $context->frontiers);
        self::assertSame($source, (array_values($context->frontiers)[0] ?? null)?->at);
        self::assertSame([], $state->memory->cells);
    }

    /**
     * @return iterable<string,array{Term,?SourceRef}>
     */
    public static function providerInvalidObservation(): iterable
    {
        foreach ([Term::constant('box'), Term::constant(4), new Term('object'), new Term('parameter', 4)] as $index => $value) {
            yield $index . ' without source' => [$value, null];
            yield $index . ' with source' => [$value, new SourceRef('test', 'a.php', 2, 5)];
        }
    }
}
