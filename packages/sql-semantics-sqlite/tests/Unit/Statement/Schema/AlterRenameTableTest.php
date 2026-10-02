<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(AlterRenameTable::class)]
#[Medium]
final class AlterRenameTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndProvidesNoDeclaration(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');
        $operation = $semantics->analyze('ALTER TABLE t RENAME TO u', [$table]);
        $resolution = $operation->facts->relation($operation->statement)->table;

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
        self::assertSame([], $operation->declarations());
        self::assertSame('t', $table->declarations()[0]->name->name->value);
    }

    public function testDeriveStatementReportsAMissingTable(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('ALTER TABLE t RENAME TO u', []);

        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheOldAndTheNewName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('alter table main.t rename to "new name"');

        self::assertInstanceOf(AlterRenameTable::class, $operation->statement);
        self::assertSame('new name', $operation->statement->newName->value);
        self::assertSame('ALTER TABLE main.t RENAME TO `new name`', $operation->toString());
    }
}
