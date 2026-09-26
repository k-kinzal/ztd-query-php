<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Model\StateStorage
 */
#[CoversClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Lattice::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class StateStorageTest extends TestCase
{
    public function testAddressDoesNotExposeAnOrdinaryProperty(): void
    {
        $context = \Tests\Fake\SolverFixture::context(configuration: new \Deriver\Api\Project\Configuration(stateSlots: [new \Deriver\Model\State\StateSlot('example.slot', 'int', \Deriver\Value\Term::constant(1))]));
        $state = new \Deriver\Internal\Solver\State();
        $storage = new \Deriver\Internal\Solver\Model\StateStorage($context);

        $storage->initialize($state, 'object');
        $state->registers['receiver'] = new \Deriver\Value\Term('object', 'object');
        $instruction = new \Deriver\Internal\IR\Instruction('slot', 'model-state-address', new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1), 'address', ['receiver'], 'example.slot');
        $storage->address($instruction, $state);
        self::assertSame('model:object', $state->addresses['address']->root);
        self::assertSame([], $state->properties);
        self::assertSame(1, $state->memory->read($state->addresses['address'])->native());
    }
    public function testInitializeDoesNotAssumeAnExternalReceiverHasItsNewObjectDefaults(): void
    {
        $context = \Tests\Fake\SolverFixture::context(configuration: new \Deriver\Api\Project\Configuration(stateSlots: [new \Deriver\Model\State\StateSlot('example.slot', 'int', \Deriver\Value\Term::constant(1))]));
        $state = new \Deriver\Internal\Solver\State();
        $storage = new \Deriver\Internal\Solver\Model\StateStorage($context);

        $storage->initialize($state, 'external', external: true);
        self::assertSame('state-input', $state->memory->read(new \Deriver\Internal\Memory\Location('model:external', ['example.slot']))->kind);
        self::assertSame('int', $state->memory->propertyTypes['model:external']['example.slot']);
    }
    public function testInitializeCopiesTheCurrentValueOnClone(): void
    {
        $context = \Tests\Fake\SolverFixture::context(configuration: new \Deriver\Api\Project\Configuration(stateSlots: [new \Deriver\Model\State\StateSlot('example.slot', 'int', \Deriver\Value\Term::constant(1))]));
        $state = new \Deriver\Internal\Solver\State();
        $storage = new \Deriver\Internal\Solver\Model\StateStorage($context);

        $storage->initialize($state, 'a');
        $state->memory->write(new \Deriver\Internal\Memory\Location('model:a', ['example.slot']), \Deriver\Value\Term::constant(4));
        $storage->initialize($state, 'b', 'a');
        self::assertSame(4, $state->memory->read(new \Deriver\Internal\Memory\Location('model:b', ['example.slot']))->native());
    }
    public function testObserveSelectsTheRegisteredAbstractRecord(): void
    {
        $context = \Tests\Fake\SolverFixture::context(configuration: new \Deriver\Api\Project\Configuration(stateSlots: [new \Deriver\Model\State\StateSlot('example.slot', 'int', \Deriver\Value\Term::constant(1))]));
        $state = new \Deriver\Internal\Solver\State();
        $storage = new \Deriver\Internal\Solver\Model\StateStorage($context);
        $storage->initialize($state, 'a');
        self::assertSame(1, $storage->observe(new \Deriver\Value\Term('object', 'a'), 'example.slot', $state)->native());
    }
}
