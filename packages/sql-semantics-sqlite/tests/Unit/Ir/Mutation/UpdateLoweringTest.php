<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update as ParsedUpdate;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateLowering
 */
final class UpdateLoweringTest extends TestCase
{
    public function testLowerUsesTheSameSlotsForReadsAndWrites(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE records (A INTEGER, b INTEGER)');
        $sql = 'UPDATE records AS r SET a = r.b, b = a';
        $operation = $semantics->inspect($sql, [$table]);
        self::assertInstanceOf(ParsedUpdate::class, $operation->statement);

        $update = (new UpdateLowering())->lower($operation, $operation->statement);

        self::assertSame('A', $update->target->columns[0]->value);
        self::assertEquals(new ColumnReference($update->assignments[0]->targetSlot), $update->assignments[1]->value);
        self::assertEquals(new ColumnReference($update->assignments[1]->targetSlot), $update->assignments[0]->value);
        $published = $semantics->analyze($sql, [$table]);
        self::assertInstanceOf(Update::class, $published);
        self::assertEquals($update, $published);
    }
}
