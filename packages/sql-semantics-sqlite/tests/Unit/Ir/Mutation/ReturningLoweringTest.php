<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturningLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ReturningLowering::class)]
#[Small]
final class ReturningLoweringTest extends TestCase
{
    public function testLowerKeepsTheExpandedTargetOrderAndExplicitLabel(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE main.entries (id BLOB, value BLOB)']);
        $statement = (new Inspection())->diagnose('DELETE FROM main.entries RETURNING *, id AS extra', $context);
        self::assertInstanceOf(Delete::class, $statement->statement);
        $operation = $statement->binding();
        $binding = new SourceBinding($operation, $statement->statement->target);

        $columns = (new ReturningLowering())->lower($operation, $statement->statement->returning, $binding);

        self::assertSame(['id', 'value', 'extra'], array_map(static fn ($column): string => $column->name->value, $columns));
        self::assertSame([0, 1, 0], array_map(static fn ($column): int => $column->reference->slot, $columns));
    }
}
