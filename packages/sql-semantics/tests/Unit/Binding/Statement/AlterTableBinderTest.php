<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\AlterTableBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTableBinder::class)]
#[Medium]
final class AlterTableBinderTest extends TestCase
{
    public function testBindClassifiesRenamesOfTablesAndColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)'));
        $table = $binder->bind('ALTER TABLE t RENAME TO u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\RenameTableStatement::class, $table);
        self::assertSame(['t'], $table->table->parts);
        self::assertSame('u', $table->newName);
        $column = $binder->bind('ALTER TABLE t RENAME a TO b');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\RenameColumnStatement::class, $column);
        self::assertSame('a', $column->column);
        self::assertSame('b', $column->newName);
        self::assertSame('ALTER TABLE "t" RENAME COLUMN "a" TO "b"', $column->toString());
    }

    public function testBindClassifiesColumnRemoval(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind('ALTER TABLE t DROP COLUMN a');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropColumnStatement::class, $statement);
        self::assertSame('a', $statement->column);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Default, $statement->behavior);
        self::assertSame('ALTER TABLE "t" DROP COLUMN "a"', $statement->toString());
    }

    public function testAddColumnBindsTheColumnAndItsConstraintsAgainstTheGrownTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind('ALTER TABLE main.t ADD COLUMN c TEXT NOT NULL CHECK (c <> a) DEFAULT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\AddColumnStatement::class, $statement);
        self::assertSame(['main', 't'], $statement->table->parts);
        self::assertSame('c', $statement->column->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $statement->column->nullability);
        self::assertCount(1, $statement->constraints);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\Check::class, $statement->constraints[0]);
        self::assertSame([], $statement->diagnostics);
        self::assertSame('ALTER TABLE "main"."t" ADD COLUMN "c" "text" NOT NULL DEFAULT 1 CHECK (("c" <> "a"))', $statement->toString());
    }

    #[TestWith(['ALTER TABLE t ADD COLUMN c INT PRIMARY KEY'])]
    #[TestWith(['ALTER TABLE t ADD COLUMN c INT UNIQUE'])]
    public function testAddColumnRejectsKeyConstraints(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::AddedColumnKey->message());
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind($sql);
    }

    public function testBindLeavesOtherDialectsToTheirOwnBinders(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t RENAME TO u');
        $origin = new \SqlSemantics\Model\Statement\Origin('s1', $source, Dialect::PostgreSql);
        self::assertNull((new AlterTableBinder())->bind($origin, $source, $context));
    }
}
