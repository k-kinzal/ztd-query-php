<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(AlterRenameColumn::class)]
#[Medium]
final class AlterRenameColumnTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndProvidesNoDeclaration(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a, b)');
        $operation = $semantics->diagnose('ALTER TABLE t RENAME COLUMN a TO c', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement)->table);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame([], $operation->declarations());
        self::assertSame('a', $table->declarations()[0]->columns[0]->name->value);
    }

    public function testDeriveStatementReportsAnUnknownOldNameAndATakenNewName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a, b)');

        self::assertInstanceOf(UnknownColumn::class, $semantics->diagnose('ALTER TABLE t RENAME x TO c', [$table])->facts->diagnostics[0]);
        self::assertInstanceOf(DuplicateColumn::class, $semantics->diagnose('ALTER TABLE t RENAME a TO B', [$table])->facts->diagnostics[0]);
    }

    public function testDeriveStatementAcceptsANewNameThatOnlyChangesTheCase(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a, b)');

        self::assertSame([], $semantics->diagnose('ALTER TABLE t RENAME a TO A', [$table])->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Inspection())->diagnose('alter table t rename column a to b');

        self::assertInstanceOf(AlterRenameColumn::class, $operation->statement);
        self::assertSame('ALTER TABLE t RENAME a TO b', $operation->toString());
    }
}
