<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\WriteSlot;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Destination::class)]
#[Small]
final class DestinationTest extends TestCase
{
    public function testToStringUsesWriteOrderAndOmitsTheReadOnlySlot(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a'), new Identifier('b'), new Identifier('c')]);
        $destination = new Destination($relation, [new WriteSlot(1, ColumnKind::Int4), new WriteSlot(0, ColumnKind::Int4)]);

        self::assertSame('"public"."t" ("b", "a")', $destination->toString());
        self::assertSame($relation, $destination->relation);
    }

    public function testColumnKindUsesWritePositionInsteadOfPhysicalSlot(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [
            new Identifier('id'), new Identifier('label'),
        ], false, [ColumnKind::Int4, ColumnKind::Text]);
        $destination = new Destination($relation, [new WriteSlot(1, ColumnKind::Text)]);

        self::assertSame(ColumnKind::Text, $destination->columnKind(0));
        self::assertNull($destination->columnKind(1));
    }
}
