<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertTargetLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(InsertTargetLowering::class)]
#[Small]
final class InsertTargetLoweringTest extends TestCase
{
    public function testLowerUsesOnlyWritableColumnsInDeclarationOrder(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE entries (First INTEGER, computed AS (First + 1), Last BLOB)']);
        $operation = (new Inspection())->inspect('INSERT INTO entries VALUES (3, 7)', $context);
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        $target = (new InsertTargetLowering())->lower($operation->binding(), $operation->statement->into, false);

        self::assertSame(['First', 'Last'], array_map(static fn (Identifier $name): string => $name->value, $target->columns));
    }
}
