<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(AlterRenameTable::class)]
#[Medium]
final class AlterRenameTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndProvidesNoDeclaration(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a)');
        $operation = $semantics->diagnose('ALTER TABLE t RENAME TO u', [$table]);
        $resolution = $operation->facts->relation($operation->statement)->table;

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
        self::assertSame([], $operation->declarations());
        self::assertSame('t', $table->declarations()[0]->name->name->value);
    }

    public function testDeriveStatementReportsAMissingTable(): void
    {
        $operation = (new Inspection())->diagnose('ALTER TABLE t RENAME TO u', []);

        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheOldAndTheNewName(): void
    {
        $operation = (new Inspection())->diagnose('alter table main.t rename to "new name"');

        self::assertInstanceOf(AlterRenameTable::class, $operation->statement);
        self::assertSame('new name', $operation->statement->newName->value);
        self::assertSame('ALTER TABLE main.t RENAME TO `new name`', $operation->toString());
    }
}
