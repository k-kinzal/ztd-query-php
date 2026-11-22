<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use Tests\Support\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DropTableLowering
 */
final class DropTableLoweringTest extends TestCase
{
    public function testLowerResolvesTheShadowingTable(): void
    {
        $semantics = new Semantics();
        $tables = [
            (new Inspection())->inspect('CREATE TABLE records (id INTEGER)'),
            (new Inspection())->inspect('CREATE TEMP TABLE records (id INTEGER)'),
        ];
        $operation = (new Inspection())->inspect('DROP TABLE records', $tables);
        self::assertInstanceOf(Drop::class, $operation->statement);

        $drop = (new DropTableLowering())->lower($operation->binding(), $operation->statement);

        self::assertSame('temp', $drop->schema->value);
        self::assertSame('records', $drop->table->value);
        $published = $semantics->analyze('DROP TABLE records', $semantics->context([
            'CREATE TABLE records (id INTEGER)',
            'CREATE TEMP TABLE records (id INTEGER)',
        ]));
        self::assertInstanceOf(DropTable::class, $published);
        self::assertEquals($drop, $published);
    }
}
