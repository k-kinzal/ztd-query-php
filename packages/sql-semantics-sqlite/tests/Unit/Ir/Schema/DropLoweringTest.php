<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropView;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DropLowering
 */
final class DropLoweringTest extends TestCase
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

        $drop = (new DropLowering())->lower($operation->binding(), $operation->statement);

        self::assertInstanceOf(DropTable::class, $drop);
        self::assertSame('temp', $drop->schema->value);
        self::assertSame('records', $drop->table->value);
        $published = $semantics->analyze('DROP TABLE records', $semantics->context([
            'CREATE TABLE records (id INTEGER)',
            'CREATE TEMP TABLE records (id INTEGER)',
        ]));
        self::assertInstanceOf(DropTable::class, $published);
        self::assertEquals($drop, $published);
    }

    public function testLowerResolvesTheShadowingView(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context([
            'CREATE VIEW records AS SELECT 1 AS id',
            'CREATE TEMP VIEW records AS SELECT 2 AS id',
        ]);

        $view = $semantics->analyze('DROP VIEW IF EXISTS records', $catalog);

        self::assertInstanceOf(DropView::class, $view);
        self::assertSame('temp', $view->schema->value);
        self::assertSame('records', $view->view->value);
        self::assertSame('DROP VIEW IF EXISTS "temp"."records"', $view->toString());
    }
}
