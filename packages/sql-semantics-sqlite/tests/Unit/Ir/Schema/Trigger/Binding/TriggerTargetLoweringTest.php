<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerTargetLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerTargetLowering::class)]
#[Small]
final class TriggerTargetLoweringTest extends TestCase
{
    public function testLowerRetainsBothTheCompleteLayoutAndWritablePositions(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id)', 'CREATE TABLE ledger (id, derived AS (id+1))']);
        $value = $semantics->analyze('CREATE TRIGGER tr DELETE ON watched BEGIN DELETE FROM ledger WHERE derived=OLD.id; END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $value);
        $step = $value->program[0];
        self::assertInstanceOf(TriggerDelete::class, $step);
        self::assertCount(2, $step->destination->table->columns);
        self::assertSame([0], $step->destination->writable);
    }
}
