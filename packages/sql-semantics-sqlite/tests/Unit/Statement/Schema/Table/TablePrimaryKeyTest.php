<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(TablePrimaryKey::class)]
#[Medium]
final class TablePrimaryKeyTest extends TestCase
{
    public function testDeriveConstraintResolvesTheKeyColumns(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, b, PRIMARY KEY (b, a))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TablePrimaryKey::class, $key);
        $resolution = $operation->facts->scalar($key->terms[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($operation->declarations()[0]->columns[1], $resolution->slot->column);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintReportsAMissingKeyColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, PRIMARY KEY (zz))', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveConstraintReportsATermThatIsNoColumnName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, PRIMARY KEY (a + 1))', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(PrimaryKeyProblem::class, $operation->facts->diagnostics[0]);
        self::assertSame(PrimaryKeyFlaw::ExpressionTerm, $operation->facts->diagnostics[0]->flaw);
    }

    public function testRenderWritesAutoincrementInsideTheParentheses(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (id integer, primary key (id desc autoincrement) on conflict replace)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TablePrimaryKey::class, $key);
        self::assertTrue($key->autoincrement);
        self::assertSame(SortDirection::Descending, $key->terms[0]->direction);
        self::assertSame(ConflictResolution::Replace, $key->conflict);
        self::assertSame('CREATE TABLE t (id integer, PRIMARY KEY (id DESC AUTOINCREMENT) ON CONFLICT REPLACE)', $operation->toString());
    }

    public function testRefusesAStringLiteralTermThatNamesAColumn(): void
    {
        $this->expectExceptionMessage('A string literal written as a key term names a column: write it as a LiteralColumn.');

        new TablePrimaryKey([new \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Collate(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral('a'), new \SqlSemantics\Statement\Identifier\Name('nocase')))]);
    }
}
