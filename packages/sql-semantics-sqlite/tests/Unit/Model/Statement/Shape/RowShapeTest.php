<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(RowShape::class)]
#[Medium]
final class RowShapeTest extends TestCase
{
    public function testCompleteHoldsWhenNoInputIsMissing(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);

        $shape = new RowShape([$slot]);

        self::assertTrue($shape->complete());
        self::assertSame([$slot], $shape->slots);
        self::assertSame([], $shape->missing);
    }

    public function testCompleteFailsWhileAnInputIsMissingAndTheKnownSlotsStayExact(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));

        $shape = new RowShape([$slot], [$missing]);

        self::assertFalse($shape->complete());
        self::assertSame([$slot], $shape->slots);
        self::assertSame([$missing], $shape->missing);
    }

    public function testCompleteOfAnAnalyzedStarOverDeclaredTables(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER, b TEXT)');

        $shape = $semantics->inspect('SELECT * FROM t', [$table])->shape();

        self::assertNotNull($shape);
        self::assertTrue($shape->complete());
        self::assertSame(['a', 'b'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
    }
}
