<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Schema\ConstraintBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ConstraintBinderTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindDiagnosesDifferentForeignKeyWidths(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind('CREATE TABLE child(x INTEGER, FOREIGN KEY(x) REFERENCES parent(a,b))', strict: false);
    }

    public function testBindUsesSqliteMatchSemantics(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind("CREATE TABLE child(x INTEGER REFERENCES parent MATCH 'ignored')");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $key = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\ForeignKey::class, $key);
        self::assertSame(\SqlSemantics\Schema\Constraint\MatchMode::Simple, $key->match);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testKeysBindColumnsInDeclarationOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT, b INT, PRIMARY KEY (b, a), UNIQUE (a))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $primary = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $primary);
        self::assertSame(['b', 'a'], array_map(static fn (\SqlSemantics\Schema\IndexElement $key): ?string => $key->value()->columnBinding()?->column->name, $primary->keys));
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Schema\Index\ColumnKey::class, $primary->keys);
        $unique = $statement->definition->table->constraints[1];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $unique);
        self::assertSame(['a'], $unique->localColumns());
    }

    public function testKeysRetainUnresolvedColumnsWithDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT, PRIMARY KEY (missing))', strict: false);
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $primary = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $primary);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $primary->keys[0]->value());
        self::assertSame(['missing'], $primary->localColumns());
        self::assertContains('unknown-column', array_column($statement->diagnostics, 'reason'));
    }

    public function testBindBindsACheckPredicate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT, CONSTRAINT positive CHECK (a > 0))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $check = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\Check::class, $check);
        self::assertSame('>', $check->predicate->spelling());
        self::assertSame('positive', $check->name);
        self::assertSame('a', $check->predicate->inputs()[0]->columnBinding()?->column->name);
    }

    public function testBindRejectsANonBooleanCheck(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('A PostgreSQL predicate must have boolean type.');
        $binder->bind('CREATE TABLE t(a INT, CHECK (a))');
    }

    public function testBindReadsCheckingTimeAndNullsDistinct(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT, UNIQUE (a) DEFERRABLE INITIALLY DEFERRED, UNIQUE (a) DEFERRABLE, UNIQUE NULLS NOT DISTINCT (a), UNIQUE (a))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $constraints = $statement->definition->table->constraints;
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $constraints);
        self::assertSame([\SqlSemantics\Schema\Constraint\CheckingTime::DeferrableDeferred, \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableImmediate, \SqlSemantics\Schema\Constraint\CheckingTime::Immediate, \SqlSemantics\Schema\Constraint\CheckingTime::Immediate], array_column($constraints, 'checking'));
        self::assertSame([true, true, false, true], array_column($constraints, 'nullsDistinct'));
    }

    public function testKeysBindColumnLevelKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT PRIMARY KEY, b INT UNIQUE)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $primary = $statement->definition->table->constraints[0];
        $unique = $statement->definition->table->constraints[1];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $primary);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $unique);
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Schema\Index\ColumnKey::class, $primary->keys);
        self::assertSame(['a'], $primary->localColumns());
        self::assertSame(['b'], $unique->localColumns());
    }

    public function testKeysKeepTheDirectionOfASqliteColumnLevelKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE TABLE t(a INTEGER CONSTRAINT pk PRIMARY KEY DESC, b INT UNIQUE)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $primary = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $primary);
        self::assertSame('pk', $primary->name);
        self::assertSame(\SqlSemantics\Schema\Index\Direction::Descending, $primary->keys[0]->direction);
    }

    public function testKeysBindAnExpressionKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t(a INT, UNIQUE KEY ((a+1)))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $unique = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $unique);
        self::assertInstanceOf(\SqlSemantics\Schema\Index\ExpressionKey::class, $unique->keys[0]);
        self::assertSame('+', $unique->keys[0]->value()->spelling());
    }


    public function testResolutionReadsTheSqliteConflictClauseOfEachConstraint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('CREATE TABLE u (id INTEGER PRIMARY KEY ON CONFLICT REPLACE AUTOINCREMENT, a INT UNIQUE ON CONFLICT FAIL, b INT, UNIQUE (a, b) ON CONFLICT ROLLBACK, CHECK (a > 1) ON CONFLICT IGNORE, CHECK (b > 0))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $keys = array_values(array_filter($statement->definition->table->constraints, static fn ($constraint): bool => $constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey || $constraint instanceof \SqlSemantics\Schema\Constraint\UniqueKey || $constraint instanceof \SqlSemantics\Schema\Constraint\Check));
        $resolutions = array_column($keys, 'onConflict');
        $response = \SqlSemantics\Model\Write\Policy\ConstraintResponse::class;
        self::assertSame([$response::Replace, $response::Fail, $response::Rollback, $response::Ignore, $response::Default], $resolutions);
        $expected = 'CREATE TABLE "main"."u"("id" "integer" NOT NULL PRIMARY KEY ON CONFLICT REPLACE AUTOINCREMENT, "a" "int", "b" "int", UNIQUE("a") ON CONFLICT FAIL, UNIQUE("a", "b") ON CONFLICT ROLLBACK, CHECK (("a" > 1)) ON CONFLICT IGNORE, CHECK (("b" > 0)))';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
