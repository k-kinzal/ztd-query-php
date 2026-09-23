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
}
