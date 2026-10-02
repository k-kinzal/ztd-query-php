<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ForeignKey;

#[CoversClass(ForeignKey::class)]
#[Medium]
final class ForeignKeyTest extends TestCase
{
    public function testDeriveConstraintAcceptsChildColumnsOfTheTable(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a, b, FOREIGN KEY (a, B) REFERENCES parent)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintReportsAChildColumnTheTableDoesNotHave(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a, FOREIGN KEY (rowid) REFERENCES parent)', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(UnknownColumn::class, $operation->facts->diagnostics[0]);
        self::assertSame('rowid', $operation->facts->diagnostics[0]->column->value);
    }

    public function testDeriveConstraintReportsDecoratedColumnsOnBothSides(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a, FOREIGN KEY (a DESC) REFERENCES parent (id ASC))', []);

        self::assertCount(2, $operation->facts->diagnostics);
        self::assertInstanceOf(DecoratedColumnName::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(DecoratedColumnName::class, $operation->facts->diagnostics[1]);
    }

    public function testRenderWritesTheColumnsTheParentAndTheDeferrability(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table c (a, b, foreign key (a, b) references parent (x, y) on delete cascade deferrable initially deferred)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->constraints[0]->items[0];
        self::assertInstanceOf(ForeignKey::class, $key);
        self::assertCount(2, $key->columns);
        self::assertTrue($key->deferrability?->deferred());
        self::assertSame('CREATE TABLE c (a, b, FOREIGN KEY (a, b) REFERENCES parent (x, y) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED)', $operation->toString());
    }
}
