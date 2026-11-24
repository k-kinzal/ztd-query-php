<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerOperands;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerOperands::class)]
#[Small]
final class TriggerOperandsTest extends TestCase
{
    public function testValueIsAClosedLiteralAfterNumericLowering(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (id)']);
        $trigger = $dialect->analyze('CREATE TRIGGER tr INSERT ON t BEGIN INSERT INTO audit VALUES ((-8)); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        self::assertInstanceOf(IntegerLiteral::class, $trigger->program[0]->fields[0]->value);
        self::assertSame('-8', $trigger->program[0]->fields[0]->value->decimal);
    }

    public function testFieldUsesTheExactRowImageAndResolvedPosition(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id, data)', 'CREATE TABLE audit (id)']);
        $trigger = $dialect->analyze('CREATE TRIGGER tr UPDATE ON t BEGIN INSERT INTO audit VALUES (OLD.DATA); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        self::assertInstanceOf(TriggerField::class, $trigger->program[0]->fields[0]->value);
        self::assertSame(RowImage::Old, $trigger->program[0]->fields[0]->value->image);
        self::assertSame(1, $trigger->program[0]->fields[0]->value->column);
    }

    public function testConditionNormalizesSymmetricNullTests(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (id)']);
        $trigger = $dialect->analyze('CREATE TRIGGER tr UPDATE ON t WHEN NULL IS NOT (NEW.id) BEGIN INSERT INTO audit VALUES (OLD.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        self::assertNotNull($trigger->condition);
        self::assertFalse($trigger->condition->isNull);
        self::assertSame(RowImage::New, $trigger->condition->field->image);
    }

    public function testUngroupKeepsWatchedReferencesInTheirOriginalSlots(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (id)']);
        $trigger = $dialect->analyze('CREATE TRIGGER tr DELETE ON t BEGIN INSERT INTO audit VALUES (((OLD.id))); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        self::assertInstanceOf(TriggerField::class, $trigger->program[0]->fields[0]->value);
        self::assertSame(0, $trigger->program[0]->fields[0]->value->column);
    }
}
