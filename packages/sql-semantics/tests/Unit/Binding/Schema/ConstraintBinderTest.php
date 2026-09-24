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
}
