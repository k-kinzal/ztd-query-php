<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DropTableLowering
 */
final class DropTableLoweringTest extends TestCase
{
    public function testLowerResolvesTheShadowingTable(): void
    {
        $semantics = new Semantics();
        $tables = [
            $semantics->inspect('CREATE TABLE records (id INTEGER)'),
            $semantics->inspect('CREATE TEMP TABLE records (id INTEGER)'),
        ];
        $operation = $semantics->inspect('DROP TABLE records', $tables);
        self::assertInstanceOf(Drop::class, $operation->statement);

        $drop = (new DropTableLowering())->lower($operation, $operation->statement);

        self::assertSame('temp', $drop->schema->value);
        self::assertSame('records', $drop->table->value);
        $published = $semantics->analyze('DROP TABLE records', $tables);
        self::assertInstanceOf(DropTable::class, $published);
        self::assertEquals($drop, $published);
    }
}
