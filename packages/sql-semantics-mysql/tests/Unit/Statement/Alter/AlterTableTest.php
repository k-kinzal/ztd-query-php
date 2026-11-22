<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterTable::class)]
#[Medium]
final class AlterTableTest extends TestCase
{
    public function testDeriveStatementReportsTheColumnProblems(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->diagnose('ALTER TABLE t DROP c, RENAME COLUMN a TO b', [$table]);

        self::assertSame(['Column c does not exist in the table.', 'Column b would be defined more than once.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testDeriveRelationAnswersTheColumnsAfterTheChange(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->diagnose('ALTER TABLE t DROP a, ADD c BIGINT FIRST, RENAME COLUMN b TO d', [$table]);
        $fact = $alter->facts->relation($alter->statement);

        self::assertSame(['c', 'd'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table->declarations()[0]->columns[1], $fact->shape->slots[1]->declaration());
    }

    public function testRenderWritesTheActionsInOrder(): void
    {
        self::assertSame('ALTER IGNORE TABLE db.t ADD COLUMN a INT, LOCK = DEFAULT PARTITION BY KEY (a)', (new Inspection('mysql-5.6.51'))->diagnose('alter ignore table db.t add a int, lock default partition by key (a)')->toString());
        self::assertSame('ALTER TABLE t ALGORITHM = COPY, COALESCE PARTITION 2', (new Inspection())->diagnose('ALTER TABLE t ALGORITHM COPY, COALESCE PARTITION 2')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE v ADD COLUMN b INT', [$table, $view])->facts->diagnostics));
    }
}
