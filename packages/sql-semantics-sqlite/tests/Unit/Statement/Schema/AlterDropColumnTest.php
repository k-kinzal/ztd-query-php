<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationRefused;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(AlterDropColumn::class)]
#[Medium]
final class AlterDropColumnTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndProvidesNoDeclaration(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a, b)');
        $operation = $semantics->analyze('ALTER TABLE t DROP COLUMN B', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement)->table);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame([], $operation->declarations());
        self::assertCount(2, $table->declarations()[0]->columns);
    }

    public function testDeriveStatementReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a, b)');

        self::assertInstanceOf(UnknownColumn::class, $semantics->analyze('ALTER TABLE t DROP c', [$table])->facts->diagnostics[0]);
    }

    public function testDeriveStatementReportsTheRemovalOfTheOnlyColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');

        self::assertInstanceOf(AlterationRefused::class, $semantics->analyze('ALTER TABLE t DROP a', [$table])->facts->diagnostics[0]);
    }

    public function testDeriveStatementReportsNothingAboutColumnsOfAnUndeclaredTable(): void
    {
        self::assertSame([], (new Semantics(Dialect::Sqlite))->analyze('ALTER TABLE t DROP COLUMN c')->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('alter table main.t drop column b');

        self::assertInstanceOf(AlterDropColumn::class, $operation->statement);
        self::assertSame('ALTER TABLE main.t DROP b', $operation->toString());
    }
}
