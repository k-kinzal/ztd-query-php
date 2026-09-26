<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\StateRegistry
 */
#[CoversClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Internal\Value\Lattice::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class StateRegistryTest extends TestCase
{
    public function testRegisterRejectsDuplicateOrUnqualifiedIdentities(): void
    {
        $registry = new \Deriver\Internal\Model\StateRegistry([new \Deriver\Model\State\StateSlot('example.slot')]);
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $registry->register(new \Deriver\Model\State\StateSlot('example.slot'));
    }
    public function testRegisterRejectsAnInitializerOutsideItsType(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Model\StateRegistry([]))->register(new \Deriver\Model\State\StateSlot('example.slot', 'int', \Deriver\Value\Term::constant('bad')));
    }
    public function testRegisterFingerprintsTheLifecycleContract(): void
    {
        $copy = new \Deriver\Internal\Model\StateRegistry([new \Deriver\Model\State\StateSlot('example.slot')]);
        $reset = new \Deriver\Internal\Model\StateRegistry([new \Deriver\Model\State\StateSlot('example.slot', clone: 'reset')]);
        self::assertNotSame($copy->manifest, $reset->manifest);
    }
}
