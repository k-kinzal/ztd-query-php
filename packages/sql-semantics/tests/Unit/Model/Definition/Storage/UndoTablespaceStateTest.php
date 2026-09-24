<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\UndoTablespaceState;

#[CoversClass(UndoTablespaceState::class)]
#[Medium]
final class UndoTablespaceStateTest extends TestCase
{
    public function testCasesSpellBothUndoStates(): void
    {
        self::assertSame(['ACTIVE', 'INACTIVE'], array_map(static fn (UndoTablespaceState $state): string => $state->value, UndoTablespaceState::cases()));
    }
}
