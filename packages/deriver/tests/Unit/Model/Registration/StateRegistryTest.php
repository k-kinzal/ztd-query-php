<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\State\StateSlot;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Registration\StateRegistry
 */
#[CoversClass(StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Lattice::class)]
#[UsesClass(\Deriver\Value\SecretFingerprint::class)]
#[UsesClass(Term::class)]
#[Small]
final class StateRegistryTest extends TestCase
{
    public function testRegisterRejectsDuplicateOrUnqualifiedIdentities(): void
    {
        $registry = new StateRegistry([new StateSlot('example.slot')]);
        $this->expectException(InvalidInputException::class);
        $registry->register(new StateSlot('example.slot'));
    }
    public function testRegisterRejectsAnInitializerOutsideItsType(): void
    {
        $this->expectException(InvalidInputException::class);
        (new StateRegistry([]))->register(new StateSlot('example.slot', 'int', Term::constant('bad')));
    }
    public function testRegisterFingerprintsTheLifecycleContract(): void
    {
        $copy = new StateRegistry([new StateSlot('example.slot')]);
        $reset = new StateRegistry([new StateSlot('example.slot', clone: 'reset')]);
        self::assertNotSame($copy->manifest, $reset->manifest);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidSlots')]
    public function testRegisterRejectsMalformedIdentitiesAndLifecycleContracts(StateSlot $slot, string $message): void
    {
        $registry = new StateRegistry([]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        $registry->register($slot);
    }

    /**
     * @return iterable<string,array{StateSlot,string}>
     */
    public static function providerInvalidSlots(): iterable
    {
        foreach (['', 'unqualified', '.name', '1bad.name', 'a.', 'a..b', 'a:b:', 'parameter:reserved', "a.b\n", 'a/b'] as $id) {
            yield 'identity ' . $id => [new StateSlot($id), 'MODEL_CONFLICT'];
        }
        yield 'clone mode' => [new StateSlot('example.slot', clone:'share'), 'MODEL_CONTRACT_VIOLATION'];
        yield 'invalidation mode' => [new StateSlot('example.slot', invalidation:'erase'), 'MODEL_CONTRACT_VIOLATION'];
        foreach (['', 'resource', 'int|', 'integer', 'int|Box'] as $type) {
            yield 'type ' . $type => [new StateSlot('example.slot', $type), 'MODEL_CONTRACT_VIOLATION'];
        }
        yield 'wrong initial type' => [new StateSlot('example.slot', 'int', Term::constant('value')), 'incompatible state initializer'];
    }

    public function testRegisterCapturesEveryContractComponentInAStableOrderedManifest(): void
    {
        $a = new StateSlot('a.slot', 'int', Term::constant(7));
        $b = new StateSlot('b:slot', 'string|null', Term::constant(null), 'reset', 'preserve');
        $first = new StateRegistry([$b, $a]);
        $second = new StateRegistry([$a, $b]);
        self::assertSame(['a.slot', 'b:slot'], array_keys($first->slots));
        self::assertSame(['slot:a.slot', 'slot:b:slot'], array_keys($first->manifest));
        self::assertSame($a, $first->slots['a.slot']);
        self::assertSame($b, $first->slots['b:slot']);
        self::assertSame($second->manifest, $first->manifest);
        self::assertStringStartsWith('int:copy:havoc:', $first->manifest['slot:a.slot']);
        self::assertStringStartsWith('string|null:reset:preserve:', $first->manifest['slot:b:slot']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSemanticChanges')]
    public function testRegisterDistinguishesEverySemanticallyDifferentSlot(StateSlot $before, StateSlot $after): void
    {
        self::assertNotSame((new StateRegistry([$before]))->manifest, (new StateRegistry([$after]))->manifest);
    }

    /**
     * @return iterable<string,array{StateSlot,StateSlot}>
     */
    public static function providerSemanticChanges(): iterable
    {
        yield 'type' => [new StateSlot('example.slot', 'int'), new StateSlot('example.slot', 'string')];
        yield 'clone' => [new StateSlot('example.slot'), new StateSlot('example.slot', clone:'reset')];
        yield 'invalidation' => [new StateSlot('example.slot'), new StateSlot('example.slot', invalidation:'preserve')];
        yield 'initializer' => [new StateSlot('example.slot', 'int', Term::constant(1)), new StateSlot('example.slot', 'int', Term::constant(2))];
        yield 'symbolic versus null' => [new StateSlot('example.slot'), new StateSlot('example.slot', initial:Term::constant(null))];
        yield 'confidential initializer' => [new StateSlot('example.slot', 'int', Term::constant(1)), new StateSlot('example.slot', 'int', Term::constant(1, true))];
        yield 'identity' => [new StateSlot('example.one'), new StateSlot('example.two')];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidSlots')]
    public function testRegisterAcceptsSupportedTypesAndQualifiedIdentities(string $id, string $type): void
    {
        $slot = new StateSlot($id, $type);
        $registry = new StateRegistry([]);
        $registry->register($slot);
        self::assertSame([$id => $slot], $registry->slots);
        self::assertSame([$type . ':copy:havoc:symbolic'], array_values($registry->manifest));
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerValidSlots(): iterable
    {
        foreach (['mixed', 'null', 'bool', 'int', 'float', 'string', 'array', 'object', 'int|string|null'] as $type) {
            yield $type => ['Vendor-1:state_2.value', $type];
        }
    }
}
