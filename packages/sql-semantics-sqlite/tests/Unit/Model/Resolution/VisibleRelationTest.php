<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Resolution\ImplicitSlot;
use SqlSemantics\Platform\Sqlite\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

#[CoversClass(VisibleRelation::class)]
#[Small]
final class VisibleRelationTest extends TestCase
{
    public function testAnOccurrenceWithoutQualifiersIsFoundByUnqualifiedNamesOnly(): void
    {
        $relation = new TableInput(new QualifiedName(new Name('t')));
        $shape = new RowShape([]);

        $visible = new VisibleRelation($relation, $shape);

        self::assertSame($relation, $visible->relation);
        self::assertSame($shape, $visible->shape);
        self::assertNull($visible->alias);
        self::assertNull($visible->name);
        self::assertSame([], $visible->hidden);
        self::assertSame([], $visible->implicit);
    }

    public function testTheQualifiersHiddenPositionsAndImplicitSlotsAreKept(): void
    {
        $implicit = new ImplicitSlot([new Name('rowid')], new OutputSlot(new Name('rowid'), new Known(Storage::Integer), Nullability::NotNull));

        $visible = new VisibleRelation(new TableInput(new QualifiedName(new Name('t')), new Name('x')), new RowShape([]), new Name('x'), new QualifiedName(new Name('t')), [1], [$implicit]);

        self::assertSame('x', $visible->alias?->value);
        self::assertSame('t', $visible->name?->name->value);
        self::assertSame([1], $visible->hidden);
        self::assertSame([$implicit], $visible->implicit);
    }

    public function testResolvedKeepsTheOriginalForQualifiedLookup(): void
    {
        $left = new TableInput(new QualifiedName(new Name('left_input')));
        $right = new TableInput(new QualifiedName(new Name('right_input')));
        $original = new OutputSlot(new Name('Key'), new Known(Storage::Text), Nullability::Nullable);
        $representative = new OutputSlot(new Name('key'), new Known(Storage::Integer), Nullability::NotNull);
        $visible = new VisibleRelation($left, new RowShape([$original]), representatives: [new ResolvedColumn($right, $representative)]);

        self::assertSame($original, $visible->resolved(0, false)->slot);
        $reached = $visible->resolved(0, true, 2);
        self::assertSame($right, $reached->relation);
        self::assertSame($representative, $reached->slot);
        self::assertSame(2, $reached->depth);
    }

    public function testStarSlotKeepsTheHeadingAndUsesTheRightValueFacts(): void
    {
        $left = new TableInput(new QualifiedName(new Name('left_input')));
        $right = new TableInput(new QualifiedName(new Name('right_input')));
        $heading = new OutputSlot(new Name('Key'), new Known(Storage::Text), Nullability::Nullable);
        $value = new OutputSlot(new Name('key'), new Known(Storage::Integer), Nullability::NotNull);
        $visible = new VisibleRelation($left, new RowShape([$heading, $heading]), representatives: [new ResolvedColumn($right, $value)]);

        self::assertSame('Key', $visible->starSlot(0)->name?->value);
        self::assertSame($value, $visible->starSlot(0)->origin);
        self::assertSame(Nullability::NotNull, $visible->starSlot(0)->nullability);
        self::assertSame($heading, $visible->starSlot(1));
    }
}
