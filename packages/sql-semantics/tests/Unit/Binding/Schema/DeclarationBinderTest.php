<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Schema\DeclarationBinder::class)]
#[Medium]
final class DeclarationBinderTest extends TestCase
{
    public function testBindResolvesConstraintsAgainstTheDeclaredColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT NOT NULL DEFAULT 1, b INT CHECK (b > a), c INT REFERENCES t(a), UNIQUE (a, b))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $table = $statement->definition->table;
        self::assertSame('public', $table->schema);
        self::assertSame('t', $table->name);
        self::assertSame(['a', 'b', 'c'], array_column($table->columns, 'name'));
        self::assertTrue($table->resolved);
        self::assertInstanceOf(\SqlSemantics\Schema\Table\PostgreSqlProperties::class, $table->properties);
        self::assertSame([\SqlSemantics\Schema\Constraint\Check::class, \SqlSemantics\Schema\Constraint\ForeignKey::class, \SqlSemantics\Schema\Constraint\UniqueKey::class], array_map(static fn (object $constraint): string => $constraint::class, $table->constraints));
        $check = $table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\Check::class, $check);
        self::assertSame('("b" > "a")', $check->predicate->structure()->toString());
        self::assertSame(['b', 'a'], array_map(static fn (\SqlSemantics\Model\ColumnBinding $binding): string => $binding->column->name, $check->predicate->lineage()));
    }

    public function testBindDiagnosesUnknownColumnsInCheckPredicates(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(a INT CHECK (missing > 1))', strict: false);
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertCount(1, $statement->definition->table->constraints);
    }

    public function testBindBindsDialectSpecificTableProperties(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLE t(a INT, KEY ix (a)) ENGINE=InnoDB COMMENT='c'");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $table = $statement->definition->table;
        self::assertInstanceOf(\SqlSemantics\Schema\Table\MySqlProperties::class, $table->properties);
        self::assertSame('InnoDB', $table->properties->engine);
        self::assertSame('c', $table->properties->comment);
        self::assertSame(['ix'], array_column($table->indexes, 'name'));
    }

    #[TestWith(['CREATE TABLE t(a INT, CONSTRAINT k UNIQUE USING INDEX i)'])]
    #[TestWith(['CREATE TABLE t(a INT, PRIMARY KEY USING INDEX i DEFERRABLE)'])]
    public function testBindDiagnosesKeysBuiltOnAnExistingIndex(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('USING INDEX must be diagnosed in CREATE TABLE.');
        } catch (\SqlSemantics\InvalidSql $error) {
            self::assertSame(\SqlSemantics\Model\Validation\InputViolation::ExistingIndexConstraint, $error->violation);
            self::assertSame('USING INDEX i', \SqlSemantics\Ast\Tree::text($error->source));
        }
    }
}
