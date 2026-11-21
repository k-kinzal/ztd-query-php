<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationRefused;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;

#[CoversClass(AlterDropColumn::class)]
#[Medium]
final class AlterDropColumnTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndProvidesNoDeclaration(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a, b)');
        $operation = $semantics->inspect('ALTER TABLE t DROP COLUMN B', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement)->table);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame([], $operation->declarations());
        self::assertCount(2, $table->declarations()[0]->columns);
    }

    public function testDeriveStatementReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a, b)');

        self::assertInstanceOf(UnknownColumn::class, $semantics->inspect('ALTER TABLE t DROP c', [$table])->facts->diagnostics[0]);
    }

    public function testDeriveStatementReportsTheRemovalOfTheOnlyColumn(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a)');

        self::assertInstanceOf(AlterationRefused::class, $semantics->inspect('ALTER TABLE t DROP a', [$table])->facts->diagnostics[0]);
    }

    public function testDeriveStatementReportsNothingAboutColumnsOfAnUndeclaredTable(): void
    {
        self::assertSame([], (new Semantics())->inspect('ALTER TABLE t DROP COLUMN c')->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Semantics())->inspect('alter table main.t drop column b');

        self::assertInstanceOf(AlterDropColumn::class, $operation->statement);
        self::assertSame('ALTER TABLE main.t DROP b', $operation->toString());
    }
}
