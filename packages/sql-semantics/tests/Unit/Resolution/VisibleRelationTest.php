<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

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
}
