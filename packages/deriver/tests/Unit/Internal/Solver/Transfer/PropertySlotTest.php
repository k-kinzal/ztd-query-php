<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Transfer\PropertySlot
 */
#[CoversClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PropertySlotTest extends TestCase
{
    public function testRetainsTheLexicalScopeAndReceiverIdentity(): void
    {
        $receiver = new \Deriver\Value\Term('object', 'one', attributes: ['class' => 'Child']);
        $slot = new \Deriver\Internal\Solver\Transfer\PropertySlot($receiver, 'value', 'ParentClass', null);
        self::assertSame($receiver, $slot->receiver);
        self::assertSame('ParentClass', $slot->scope);
        self::assertSame('value', $slot->name);
        self::assertFalse($slot->static);
    }
}
