<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\RelationChangeLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTable;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RelationChangeLowering::class)]
#[Medium]
final class RelationChangeLoweringTest extends TestCase
{
    public function testLowerKeepsTheDistinctOperationRoles(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE app.report (id integer)', 'CREATE VIEW app.report_view AS SELECT 6 AS id']);

        self::assertInstanceOf(DropTable::class, $semantics->analyze('DROP TABLE app.report', $declarations));
        self::assertInstanceOf(DropView::class, $semantics->analyze('DROP VIEW app.report_view', $declarations));
        self::assertInstanceOf(RenameTable::class, $semantics->analyze('RENAME TABLE app.report TO app.archived', $declarations));
        self::assertInstanceOf(TruncateTable::class, $semantics->analyze('TRUNCATE app.report', $declarations));
    }

    public function testAppliesOnlyToNativeRelationChanges(): void
    {
        $inspection = new Inspection();
        $change = $inspection->inspect('DROP INDEX named ON app.report')->statement;
        $select = $inspection->inspect('SELECT 1')->statement;
        $lowering = new RelationChangeLowering();

        self::assertTrue($lowering->applies($change));
        self::assertFalse($lowering->applies($select));
    }
}
