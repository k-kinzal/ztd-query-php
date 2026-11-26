<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerMutationOperands;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerEquality;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerMutationOperands::class)]
#[Small]
final class TriggerMutationOperandsTest extends TestCase
{
    public function testValuePrefersTheBoundLocalOccurrenceOverAnImageName(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id, other)', 'CREATE TABLE new (id, value)']);
        $root = $semantics->analyze('CREATE TRIGGER tr UPDATE ON watched BEGIN UPDATE new SET value=NEW.id; END', $declarations);
        self::assertInstanceOf(CreateTrigger::class, $root);
        $step = $root->program[0];
        self::assertInstanceOf(TriggerUpdate::class, $step);

        self::assertInstanceOf(TriggerTargetField::class, $step->assignments[0]->value);
        self::assertSame(0, $step->assignments[0]->value->column);
    }

    public function testFieldResolvesAnImageWhenTheLocalLayoutHasNoSuchName(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id, other)', 'CREATE TABLE new (id, value)']);
        $root = $semantics->analyze('CREATE TRIGGER tr UPDATE ON watched BEGIN UPDATE new SET value=NEW.other; END', $declarations);
        self::assertInstanceOf(CreateTrigger::class, $root);
        $step = $root->program[0];
        self::assertInstanceOf(TriggerUpdate::class, $step);

        self::assertInstanceOf(TriggerField::class, $step->assignments[0]->value);
        self::assertSame(1, $step->assignments[0]->value->column);
    }

    public function testFilterPreservesImageFirstEquality(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id, other)', 'CREATE TABLE new (id, value)']);
        $root = $semantics->analyze('CREATE TRIGGER tr UPDATE ON watched BEGIN UPDATE new SET value=33 WHERE OLD.other = id; END', $declarations);
        self::assertInstanceOf(CreateTrigger::class, $root);
        $step = $root->program[0];
        self::assertInstanceOf(TriggerUpdate::class, $step);

        self::assertInstanceOf(TriggerEquality::class, $step->where);
        self::assertFalse($step->where->targetFirst);
        self::assertSame(RowImage::Old, $step->where->image->image);
    }

    public function testNullTestPreservesTheImageAndNegativePolarity(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id, other)', 'CREATE TABLE new (id, value)']);
        $root = $semantics->analyze('CREATE TRIGGER tr UPDATE ON watched BEGIN UPDATE new SET value=33 WHERE NEW.other IS NOT NULL; END', $declarations);
        self::assertInstanceOf(CreateTrigger::class, $root);
        $step = $root->program[0];
        self::assertInstanceOf(TriggerUpdate::class, $step);

        self::assertInstanceOf(TriggerCondition::class, $step->where);
        self::assertFalse($step->where->isNull);
        self::assertSame(1, $step->where->field->column);
    }
}
