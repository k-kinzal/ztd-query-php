<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerChecks;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerPlacement;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerTiming;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerUpdateName;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerChecks::class)]
#[Small]
final class TriggerChecksTest extends TestCase
{
    public function testPlacementAcceptsTheWatchedSchemaForAStoredTrigger(): void
    {
        $schema = new Identifier('archive');
        $table = new TableSource($schema, new Identifier('t'), [new Identifier('id')]);
        (new TriggerChecks())->placement($schema, new Identifier('tr'), TriggerPlacement::Stored, $table, TriggerTiming::After);

        self::assertSame($schema, $table->schema);
    }

    public function testFieldAcceptsBothUpdateImages(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('t'), [new Identifier('id')]);
        $field = new TriggerField(RowImage::Old, 0);
        (new TriggerChecks())->field($field, TriggerEvent::Update, $table);

        self::assertSame(RowImage::Old, $field->image);
    }

    public function testSelectorsKeepKnownAbsentNames(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('t'), [new Identifier('id')]);
        $selector = new TriggerUpdateName(new Identifier('next'));
        (new TriggerChecks())->selectors([$selector], TriggerEvent::Update, $table);

        self::assertInstanceOf(Identifier::class, $selector->selector);
        self::assertSame('next', $selector->selector->value);
    }

    public function testProgramAcceptsRepeatedWritesToOnePhysicalLayout(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('t'), [new Identifier('id')]);
        $field = new TriggerInsertField(0, new TriggerField(RowImage::New, 0));
        $step = new TriggerInsert(new TriggerDestination($table, [0]), [$field]);
        $sameTable = new TableSource(new Identifier('main'), new Identifier('t'), [new Identifier('id')]);
        $later = new TriggerInsert(new TriggerDestination($sameTable, [0]), [$field]);
        (new TriggerChecks())->program([$step, $later], TriggerPlacement::Stored, $table, TriggerEvent::Insert);

        self::assertSame($field, $step->fields[0]);
    }

    public function testLayoutPreservesDistinctMembersThatLookLikeNumbers(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('t'), [new Identifier('01'), new Identifier('1')]);

        self::assertSame(['01', '1'], (new TriggerChecks())->layout($table));
    }

    public function testIdentityDelimitsBothCanonicalNameParts(): void
    {
        $table = new TableSource(new Identifier('Archive'), new Identifier('A.B'), [new Identifier('id')]);

        self::assertSame("archive\0a.b", (new TriggerChecks())->identity($table));
    }

    public function testInsertionImagesContinuesToResolveAgainstTheWatchedRow(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('watched'), [new Identifier('id')]);
        $field = new TriggerInsertField(0, new TriggerField(RowImage::New, 0));
        $action = new TriggerInsert(new TriggerDestination($table, [0]), [$field]);

        (new TriggerChecks())->insertionImages($action, TriggerEvent::Insert, $table);

        self::assertSame($field, $action->fields[0]);
    }
}
