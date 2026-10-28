<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(AlterTable::class)]
#[Medium]
final class AlterTableTest extends TestCase
{
    public function testDeriveStatementRecordsPartitionMaintenanceResults(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $check = $semantics->analyze('ALTER TABLE missing CHECK PARTITION ALL');
        self::assertInstanceOf(M\Statement::class, $check);

        self::assertSame(4, $check->fields()?->count());
        self::assertSame('Msg_text', $check->field(3)->slot->name?->value);
        $rebuild = $semantics->analyze('ALTER TABLE missing REBUILD PARTITION ALL');
        self::assertInstanceOf(M\Statement::class, $rebuild);
        self::assertNull($rebuild->fields());
    }

    public function testDeriveStatementReportsAnExchangeWithTheSameTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $alter = $semantics->analyze('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE t', [$table]);
        self::assertInstanceOf(M\Statement::class, $alter);

        self::assertSame(["Not unique table/alias: 't'."], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->diagnostics()));
    }

    public function testDeriveStatementReportsTheColumnProblems(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t DROP c, RENAME COLUMN a TO b', [$table]);
        self::assertInstanceOf(M\Statement::class, $alter);

        self::assertSame(['Column c does not exist in the table.', 'Column b would be defined more than once.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->diagnostics()));
    }

    public function testDeriveRelationAnswersTheColumnsAfterTheChange(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t DROP a, ADD c BIGINT FIRST, RENAME COLUMN b TO d', [$table]);
        self::assertInstanceOf(M\Statement::class, $alter);
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        $fact = $alter->relationInformation();

        self::assertSame(['c', 'd'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table->declarations()[0]->columns[1], $fact->shape->slots[1]->declaration());
    }

    public function testRenderWritesTheActionsInOrder(): void
    {
        self::assertSame('ALTER IGNORE TABLE db.t ADD a INT, LOCK DEFAULT PARTITION BY KEY (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter ignore table db.t add a int, lock default partition by key (a)')->toString());
        self::assertSame('ALTER TABLE t ALGORITHM COPY, COALESCE PARTITION 2', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM COPY, COALESCE PARTITION 2')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        $alter = $semantics->analyze('ALTER TABLE v ADD COLUMN b INT', [$table, $view]);
        self::assertInstanceOf(M\Statement::class, $alter);
        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $alter->diagnostics()));
    }

    public function testDeriveStatementWarnsOfIgnoreIn56(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $tables = $semantics->analyze('CREATE TABLE t (a INT)')->declarations();

        $alter = $semantics->analyze('ALTER IGNORE TABLE t ADD b INT', $tables);
        self::assertInstanceOf(M\Statement::class, $alter);
        self::assertSame(["'IGNORE' is deprecated and will be removed in a future release."], array_map(static fn ($warning): string => $warning->message(), $alter->warnings()));
    }
    public function testDeriveStatementChecksAddedAndChangedTypesAfterTheirNotices(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $parser = $semantics->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $sql = 'ALTER TABLE missing ADD (a INT(11), b BIT(65)), CHANGE c d INT(256)';
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $sql, strlen($sql), $semantics->profile());

        self::assertSame(['Integer display width is deprecated and will be removed in a future release.', 'Integer display width is deprecated and will be removed in a future release.', "Display width out of range for column 'b' (max = 64)"], array_map(static fn ($warning): string => $warning instanceof M\Notice\ParseStop ? $warning->name : ($warning instanceof M\Notice\Deprecated ? $warning->value : $warning->message()), array_column($notices, 1)));
    }
    public function testDeriveProgramAndDeriveRelationKeepTheTargetAndCompleteActions(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER()), ADD a INT');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        $alter = $operation->body;
        self::assertInstanceOf(AlterTable::class, $alter);
        self::assertCount(2, $alter->commands);
        self::assertTrue($alter->hasBinding());
    }

    public function testRenderRetainsColumnAttributesAndActionOrder(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER()) STORED NOT NULL, ADD a INT');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertSame('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS (USER()) STORED NOT NULL, ADD a INT', $operation->toString());
    }

    public function testHasExecutionConditionDistinguishesAnOrdinaryActionList(): void
    {
        self::assertFalse((new AlterTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), []))->hasExecutionCondition());
    }
    public function testDeriveRelationResolvesTheChangedTableShape(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.7.44');
        $table = $semantics->analyze('CREATE TABLE t(g INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER())', [$table]);
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        $alter = $operation->body;
        self::assertInstanceOf(AlterTable::class, $alter);
        $derivation = new \SqlSemantics\Construction\Derivation($semantics->context([$table]));
        self::assertInstanceOf(DeclaredTable::class, $alter->deriveRelation($derivation, $derivation->environment())->table);
    }
}
