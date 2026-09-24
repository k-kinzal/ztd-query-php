<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\Constraint\MatchMode;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Constraints;

#[CoversClass(Constraints::class)]
#[Medium]
final class ConstraintsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'a INT, b INT, CONSTRAINT u UNIQUE NULLS NOT DISTINCT (a, b)', 'CONSTRAINT "u" UNIQUE NULLS NOT DISTINCT("a", "b")'])]
    #[TestWith([Dialect::PostgreSql, 'a INT, PRIMARY KEY (a) DEFERRABLE INITIALLY IMMEDIATE', 'PRIMARY KEY("a") DEFERRABLE INITIALLY IMMEDIATE'])]
    #[TestWith([Dialect::PostgreSql, 'b INT, UNIQUE (b) DEFERRABLE INITIALLY DEFERRED', 'UNIQUE("b") DEFERRABLE INITIALLY DEFERRED'])]
    #[TestWith([Dialect::PostgreSql, 'a INT, b INT, FOREIGN KEY (a, b) REFERENCES u (x, y) MATCH PARTIAL ON DELETE SET NULL (a, b)', 'FOREIGN KEY("a", "b") REFERENCES "u"("x", "y") MATCH PARTIAL ON DELETE SET NULL("a", "b") ON UPDATE NO ACTION'])]
    #[TestWith([Dialect::MySql, 'a INT, b INT, CONSTRAINT chk CHECK (a > b) NOT ENFORCED', 'CONSTRAINT `chk` CHECK ((`a` > `b`)) NOT ENFORCED'])]
    #[TestWith([Dialect::MySql, 'a INT, FOREIGN KEY (a) REFERENCES u (x) ON DELETE NO ACTION ON UPDATE SET NULL', 'FOREIGN KEY(`a`) REFERENCES `u`(`x`) ON DELETE NO ACTION ON UPDATE SET NULL'])]
    #[TestWith([Dialect::Sqlite, 'a INT, b INT, CONSTRAINT fk FOREIGN KEY (a) REFERENCES u (x) ON DELETE CASCADE ON UPDATE NO ACTION DEFERRABLE INITIALLY DEFERRED', 'CONSTRAINT "fk" FOREIGN KEY("a") REFERENCES "u"("x") ON DELETE CASCADE ON UPDATE NO ACTION DEFERRABLE INITIALLY DEFERRED'])]
    #[TestWith([Dialect::Sqlite, 'a INT, b INT, CHECK (a > b)', 'CHECK (("a" > "b"))'])]
    public function testWriteSerializesEachConstraintTypeFromItsOperands(Dialect $dialect, string $body, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind('CREATE TABLE t (' . $body . ')');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $constraints = $statement->definition->table->constraints;
        self::assertSame($expected, Constraints::write($constraints[count($constraints) - 1], $dialect)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateTableStatement::class, $rebound);
        $again = $rebound->definition->table->constraints;
        self::assertSame($expected, Constraints::write($again[count($again) - 1], $dialect)->toString());
    }

    public function testForeignKeyKeepsMatchModeActionsAndChecking(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TABLE t (d INT REFERENCES u (id) MATCH FULL ON DELETE SET NULL ON UPDATE CASCADE DEFERRABLE INITIALLY IMMEDIATE)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $key = $statement->definition->table->constraints[0];
        self::assertInstanceOf(ForeignKey::class, $key);
        self::assertSame('FOREIGN KEY("d") REFERENCES "u"("id") MATCH FULL ON DELETE SET NULL ON UPDATE CASCADE DEFERRABLE INITIALLY IMMEDIATE', Constraints::foreignKey($key, Dialect::PostgreSql)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateTableStatement::class, $rebound);
        $again = $rebound->definition->table->constraints[0];
        self::assertInstanceOf(ForeignKey::class, $again);
        self::assertSame(MatchMode::Full, $again->match);
        self::assertSame(CheckingTime::DeferrableImmediate, $again->checking);
    }

    public function testColumnWritesTheInlineFormOfEachConstraint(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a INT CONSTRAINT pk PRIMARY KEY DEFERRABLE INITIALLY DEFERRED, c INT CHECK (c > 0), e INT CONSTRAINT fk REFERENCES u)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $constraints = $statement->definition->table->constraints;
        self::assertSame('CONSTRAINT "pk" PRIMARY KEY DEFERRABLE INITIALLY DEFERRED', Constraints::column($constraints[0], Dialect::PostgreSql)->toString());
        self::assertSame('CHECK (("c" > 0))', Constraints::column($constraints[1], Dialect::PostgreSql)->toString());
        self::assertSame('CONSTRAINT "fk" REFERENCES "u" ON DELETE NO ACTION ON UPDATE NO ACTION', Constraints::column($constraints[2], Dialect::PostgreSql)->toString());
    }

    public function testColumnsQuotesEachNameForTheDialect(): void
    {
        self::assertSame('(`a`, `b`)', Constraints::columns(['a', 'b'], Dialect::MySql)->toString());
        self::assertSame('("a")', Constraints::columns(['a'], Dialect::PostgreSql)->toString());
    }

    public function testCheckingWritesOnlyDeferrableSchedules(): void
    {
        self::assertSame('', Constraints::checking(CheckingTime::Immediate)->toString());
        self::assertSame('DEFERRABLE INITIALLY IMMEDIATE', Constraints::checking(CheckingTime::DeferrableImmediate)->toString());
        self::assertSame('DEFERRABLE INITIALLY DEFERRED', Constraints::checking(CheckingTime::DeferrableDeferred)->toString());
    }

    public function testWriteSpellsANotEnforcedCheck(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1 > 0');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $check = new \SqlSemantics\Schema\Constraint\Check($statement->outputs[0]->expression, false, false, 'c');
        self::assertSame('CONSTRAINT `c` CHECK ((1 > 0)) NOT ENFORCED', Constraints::write($check, Dialect::MySql)->toString());
    }

    public function testWriteSpellsANonInheritedCheck(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 > 0');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $check = new \SqlSemantics\Schema\Constraint\Check($statement->outputs[0]->expression, true, true);
        self::assertSame('CHECK ((1 > 0)) NO INHERIT', Constraints::write($check, Dialect::PostgreSql)->toString());
    }

    public function testDirectionWritesOnlyASqlitePrimaryKeyDirection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE TABLE t (a INT UNIQUE, b TEXT, PRIMARY KEY (b DESC))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $constraints = $statement->definition->table->constraints;
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $constraints[0]);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $constraints[1]);
        self::assertSame([], Constraints::direction($constraints[0], Dialect::Sqlite));
        self::assertSame('PRIMARY KEY DESC', Constraints::column($constraints[1], Dialect::Sqlite)->toString());
        self::assertSame([], Constraints::direction($constraints[1], Dialect::PostgreSql));
    }

    public function testColumnWritesUniqueAndPrimaryKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a INT UNIQUE, b INT PRIMARY KEY)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $constraints = $statement->definition->table->constraints;
        self::assertSame(['UNIQUE', 'PRIMARY KEY'], [Constraints::column($constraints[0], Dialect::PostgreSql)->toString(), Constraints::column($constraints[1], Dialect::PostgreSql)->toString()]);
    }
}
