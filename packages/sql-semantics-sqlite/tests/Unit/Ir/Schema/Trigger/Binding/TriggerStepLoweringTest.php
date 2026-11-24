<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerStepLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerStepLowering::class)]
#[Small]
final class TriggerStepLoweringTest extends TestCase
{
    public function testLowerRetainsPhysicalSlotsAcrossAGeneratedDestinationColumn(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (a)', 'CREATE TABLE audit (a, generated AS (a + 1), c)']);
        $result = $dialect->analyze('CREATE TRIGGER tr INSERT ON t BEGIN INSERT INTO audit(c,a) VALUES (new.a,7); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertInstanceOf(TriggerInsert::class, $result->program[0]);
        self::assertSame([0, 2], $result->program[0]->destination->writable);
        self::assertSame(2, $result->program[0]->rows[0]->fields[0]->column);
        self::assertSame('generated', $result->program[0]->destination->table->columns[1]->value);
    }

    public function testLowerKeepsAllTuplesInsideOneInsertionAction(): void
    {
        $dialect = new Semantics();
        $declarations = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (source, note)']);
        $result = $dialect->analyze('CREATE TRIGGER tr INSERT ON t BEGIN INSERT OR FAIL INTO audit(note, source) VALUES (NULL,NEW.id),(NULL,4); END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertCount(1, $result->program);
        $statement = $result->program[0];
        self::assertInstanceOf(TriggerInsert::class, $statement);
        self::assertCount(2, $statement->rows);
        self::assertSame([1, 0], $statement->rows[1]->columns());
        self::assertStringContainsString('INSERT OR FAIL INTO "audit" ("note", "source") VALUES (NULL, NEW."id"), (NULL, 4);', $result->toString());
    }
}
