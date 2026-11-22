<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Destination::class)]
#[Small]
final class DestinationTest extends TestCase
{
    public function testToStringUsesWriteOrderAndOmitsTheReadOnlySlot(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a'), new Identifier('b'), new Identifier('c')]);
        $destination = new Destination($relation, [new ColumnReference(1), new ColumnReference(0)]);

        self::assertSame('"public"."t" ("b", "a")', $destination->toString());
        self::assertSame($relation, $destination->relation);
    }
}
