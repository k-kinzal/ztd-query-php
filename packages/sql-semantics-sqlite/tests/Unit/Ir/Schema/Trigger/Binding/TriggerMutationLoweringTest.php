<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerMutationLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;

#[CoversClass(TriggerMutationLowering::class)]
#[Small]
final class TriggerMutationLoweringTest extends TestCase
{
    public function testLowerUsesTheBoundWriteSlot(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id)', 'CREATE TABLE ledger (id, last_value)']);
        $value = $semantics->analyze('CREATE TRIGGER tr UPDATE ON watched BEGIN UPDATE ledger SET last_value = OLD.id; END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $value);
        $step = $value->program[0];
        self::assertInstanceOf(TriggerUpdate::class, $step);
        self::assertSame(1, $step->assignments[0]->column);
    }

    public function testConflictRetainsTheWrittenNativeChoice(): void
    {
        self::assertSame(UpdateConflict::Ignore, (new TriggerMutationLowering())->conflict(ConflictResolution::Ignore));
    }
}
