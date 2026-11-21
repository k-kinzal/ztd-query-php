<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertLowering
 */
final class InsertLoweringTest extends TestCase
{
    public function testLowerResolvesTheWriteToTheDeclaredSlots(): void
    {
        $semantics = new Semantics();
        $schema = $semantics->inspect('CREATE TABLE records (Item INTEGER, Cost INTEGER)');
        $sql = 'INSERT OR IGNORE INTO records (COST, item) VALUES (2, 3)';
        $operation = $semantics->inspect($sql, [$schema]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        $insert = (new InsertLowering())->lower($operation, $operation->statement);

        self::assertSame(InsertConflict::Ignore, $insert->conflict);
        self::assertSame('Cost', $insert->target->columns[0]->value);
        self::assertSame('Item', $insert->target->columns[1]->value);
        $published = $semantics->analyze($sql, [$schema]);
        self::assertInstanceOf(Insert::class, $published);
        self::assertEquals($insert, $published);
    }
}
