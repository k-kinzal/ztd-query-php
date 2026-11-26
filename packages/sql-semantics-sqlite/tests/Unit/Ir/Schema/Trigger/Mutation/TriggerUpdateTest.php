<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerUpdate::class)]
#[Small]
final class TriggerUpdateTest extends TestCase
{
    public function testKeepsAssignmentOrderAndConflictPolicy(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id)', 'CREATE TABLE audit (id, value)']);
        $result = $semantics->analyze('CREATE TRIGGER tr INSERT ON watched BEGIN UPDATE OR FAIL audit SET value=NEW.id, id=4; END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $result);
        $step = $result->program[0];
        self::assertInstanceOf(TriggerUpdate::class, $step);
        self::assertSame([1, 0], array_column($step->assignments, 'column'));
        self::assertSame(UpdateConflict::Fail, $step->conflict);
    }
}
