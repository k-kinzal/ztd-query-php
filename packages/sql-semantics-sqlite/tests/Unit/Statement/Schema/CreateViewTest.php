<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\ColumnCountMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CreateView::class)]
#[Medium]
final class CreateViewTest extends TestCase
{
    public function testDeriveStatementDeclaresTheColumnsOfTheQueryOutput(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $source = $semantics->analyze('CREATE TABLE s (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('CREATE VIEW v AS SELECT a, b AS label FROM s', [$source]);
        $view = $operation->declarations()[0];

        self::assertTrue($view->complete);
        self::assertSame([], $view->implicit);
        self::assertSame(['a', 'label'], array_map(static fn (object $column): string => $column->name->value, $view->columns));
        self::assertSame('INTEGER', $view->columns[0]->type->name());
        self::assertNotSame($source->declarations()[0]->columns[0]->type, $view->columns[0]->type);
        self::assertSame(Nullability::NotNull, $view->columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $view->columns[1]->nullability);
        self::assertNull($operation->shape());
    }

    public function testDeriveStatementNamesTheColumnsByTheColumnList(): void
    {
        $view = (new Semantics(Dialect::Sqlite))->analyze('CREATE VIEW v (x, y) AS SELECT 1, 2')->declarations()[0];

        self::assertTrue($view->complete);
        self::assertSame(['x', 'y'], array_map(static fn (object $column): string => $column->name->value, $view->columns));
    }

    public function testDeriveStatementReportsAColumnListOfAnotherLength(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE VIEW v (x) AS SELECT 1, 2');

        self::assertInstanceOf(ColumnCountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertFalse($operation->declarations()[0]->complete);
    }

    public function testDeriveStatementReportsADecoratedNameInTheColumnList(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE VIEW v (x DESC) AS SELECT 1');

        self::assertInstanceOf(DecoratedColumnName::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveStatementMarksTheDeclarationIncompleteWhenTheQueryShapeIsOpen(): void
    {
        $view = (new Semantics(Dialect::Sqlite))->analyze('CREATE VIEW v AS SELECT * FROM undeclared')->declarations()[0];

        self::assertFalse($view->complete);
        self::assertSame([], $view->columns);
    }

    public function testDeriveStatementDeclaresATemporaryViewInTheTempSchema(): void
    {
        $view = (new Semantics(Dialect::Sqlite))->analyze('CREATE TEMPORARY VIEW v AS SELECT 1 AS one')->declarations()[0];

        self::assertSame('temp', $view->name->schema?->value);
    }

    #[TestWith(["CREATE VIEW v AS SELECT a+1, \"zz\", A COLLATE nocase, likely(b), true, 1 AS false, x'0aff' FROM t"])]
    #[TestWith(['CREATE VIEW v AS SELECT 1 + /* c */ 1 UNION SELECT 2'])]
    #[TestWith(['CREATE VIEW v(true, x, X) AS SELECT 1, 2, 3'])]
    public function testDeriveStatementNamesTheColumnsAsSqliteDoes(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $view = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)')]);
        $source = new PDO('sqlite::memory:');
        $rendered = new PDO('sqlite::memory:');
        $source->exec('CREATE TABLE t (a INTEGER, b TEXT); ' . $sql);
        $rendered->exec('CREATE TABLE t (a INTEGER, b TEXT); ' . $view->toString());
        $original = $source->query('SELECT * FROM v');
        $rebuilt = $rendered->query('SELECT * FROM v');
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        $names = array_map(static fn (int $position): mixed => $original->getColumnMeta($position) === false ? null : $original->getColumnMeta($position)['name'], range(0, $original->columnCount() - 1));

        self::assertSame($names, array_map(static fn (int $position): mixed => $rebuilt->getColumnMeta($position) === false ? null : $rebuilt->getColumnMeta($position)['name'], range(0, $rebuilt->columnCount() - 1)));
        self::assertSame($names, array_map(static fn (Column $column): string => $column->name->value, $view->declarations()[0]->columns));
    }

    public function testRenderWritesTheColumnListAndTheQuery(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create view if not exists main.v (x, y) as select 1, 2');

        self::assertInstanceOf(CreateView::class, $operation->statement);
        self::assertCount(2, $operation->statement->columns ?? []);
        self::assertSame('CREATE VIEW IF NOT EXISTS main.v (x, y) AS SELECT 1, 2', $operation->toString());
    }
}
