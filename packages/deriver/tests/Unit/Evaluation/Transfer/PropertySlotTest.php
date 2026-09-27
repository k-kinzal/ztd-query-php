<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Transfer\PropertySlot
 */
#[CoversClass(PropertySlot::class)]
#[UsesClass(Term::class)]
#[Small]
final class PropertySlotTest extends TestCase
{
    public function testRetainsTheLexicalScopeAndReceiverIdentity(): void
    {
        $receiver = new Term('object', 'one', attributes: ['class' => 'Child']);
        $slot = new PropertySlot($receiver, 'value', 'ParentClass', null);
        self::assertSame($receiver, $slot->receiver);
        self::assertSame('ParentClass', $slot->scope);
        self::assertSame('value', $slot->name);
        self::assertFalse($slot->static);
    }
}
