<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\DeleteLowering;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(DeleteLowering::class)]
#[Small]
final class DeleteLoweringTest extends TestCase
{
    public function testLowerBindsTheTargetAliasToItsColumnSlot(): void
    {
        $sqlite = new Semantics();
        $context = $sqlite->context(['CREATE TABLE entries (id INTEGER, active INTEGER)']);
        $result = $sqlite->analyze('DELETE FROM entries AS e WHERE e.active = 1', $context);

        self::assertInstanceOf(Delete::class, $result);
        self::assertSame('entries', $result->target->table->value);
        self::assertSame('active', $result->target->columns[0]->value);
        self::assertInstanceOf(Comparison::class, $result->where);
        self::assertInstanceOf(ColumnReference::class, $result->where->left);
        self::assertSame(0, $result->where->left->slot);
    }
}
