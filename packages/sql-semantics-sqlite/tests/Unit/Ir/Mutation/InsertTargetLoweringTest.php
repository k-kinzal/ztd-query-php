<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertTargetLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(InsertTargetLowering::class)]
#[Small]
final class InsertTargetLoweringTest extends TestCase
{
    public function testLowerBindsExplicitColumnsInInputOrder(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE entries (First INTEGER, computed AS (First + 1), Last BLOB)']);
        $operation = (new Inspection())->inspect('INSERT INTO entries (Last, First) VALUES (3, 7)', $context);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        $into = $operation->statement->into;
        $binding = new SourceBinding($operation->binding(), $into->target);
        $writes = (new InsertTargetLowering())->lower($binding, $into, false);
        $target = $binding->source();

        self::assertSame([0, 1], array_map(static fn (ColumnReference $write): int => $write->slot, $writes));
        self::assertNotNull($target);
        self::assertSame('Last', $target->columns[0]->value);
        self::assertSame('First', $target->columns[1]->value);
    }
}
