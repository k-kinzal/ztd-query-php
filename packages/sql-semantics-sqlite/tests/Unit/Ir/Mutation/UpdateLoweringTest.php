<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update as ParsedUpdate;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateLowering
 */
final class UpdateLoweringTest extends TestCase
{
    public function testLowerUsesTheSameSlotsForReadsAndWrites(): void
    {
        $semantics = new Semantics();
        $table = (new Inspection())->inspect('CREATE TABLE records (A INTEGER, b INTEGER)');
        $sql = 'UPDATE records AS r SET a = r.b, b = a';
        $operation = (new Inspection())->inspect($sql, [$table]);
        self::assertInstanceOf(ParsedUpdate::class, $operation->statement);

        $update = (new UpdateLowering())->lower($operation->binding(), $operation->statement);

        self::assertSame('A', $update->target->columns[0]->value);
        self::assertEquals(new ColumnReference($update->assignments[0]->targetSlot), $update->assignments[1]->value);
        self::assertEquals(new ColumnReference($update->assignments[1]->targetSlot), $update->assignments[0]->value);
        $published = $semantics->analyze($sql, $semantics->context(['CREATE TABLE records (A INTEGER, b INTEGER)']));
        self::assertInstanceOf(Update::class, $published);
        self::assertEquals($update, $published);
    }

    public function testLowerPreservesTheRollbackPolicy(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE records (id INTEGER PRIMARY KEY)']);
        $update = $semantics->analyze('UPDATE OR ROLLBACK records SET id = 2 WHERE id = 1', $context);

        self::assertInstanceOf(Update::class, $update);
        self::assertSame(UpdateConflict::Rollback, $update->conflict);
        self::assertStringStartsWith('UPDATE OR ROLLBACK ', $update->toString());
    }
}
