<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(AlterTable::class)]
#[Medium]
final class AlterTableTest extends TestCase
{
    public function testDeriveStatementReportsTheColumnProblems(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t DROP c, RENAME COLUMN a TO b', [$table]);

        self::assertSame(['Column c does not exist in the table.', 'Column b would be defined more than once.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testDeriveRelationAnswersTheColumnsAfterTheChange(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t DROP a, ADD c BIGINT FIRST, RENAME COLUMN b TO d', [$table]);
        $fact = $alter->facts->relation($alter->statement);

        self::assertSame(['c', 'd'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table->declarations()[0]->columns[1], $fact->shape->slots[1]->declaration());
    }

    public function testRenderWritesTheActionsInOrder(): void
    {
        self::assertSame('ALTER IGNORE TABLE db.t ADD COLUMN a INT, LOCK = DEFAULT PARTITION BY KEY (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter ignore table db.t add a int, lock default partition by key (a)')->toString());
        self::assertSame('ALTER TABLE t ALGORITHM = COPY, COALESCE PARTITION 2', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM COPY, COALESCE PARTITION 2')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE v ADD COLUMN b INT', [$table, $view])->facts->diagnostics));
    }
}
