<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\Mutation\DeleteTableStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Trigger\SqliteBody;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqliteBody::class)]
#[Medium]
final class SqliteBodyTest extends TestCase
{
    public function testRetainsTheOrderedProgramOfSteps(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; SELECT 1; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        self::assertCount(2, $statement->body->steps);
        self::assertInstanceOf(UpdateTableStatement::class, $statement->body->steps[0]);
        self::assertInstanceOf(BoundSelect::class, $statement->body->steps[1]);
        $changed = $statement->withBody(new SqliteBody([$statement->body->steps[1]]));
        self::assertCount(1, $changed->body->steps);
        self::assertCount(2, $statement->body->steps);
        self::assertSame('CREATE TRIGGER "tr" AFTER UPDATE OF "id" ON "main"."t" FOR EACH ROW BEGIN SELECT 1; END', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString())->toString());
    }

    public function testRejectsAnEmptyProgram(): void
    {
        $this->expectException(InvalidStructure::class);
        new SqliteBody([]);
    }

    public function testRejectsAMutationStepWithAReturningClause(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $step = (new Binder($schema))->bind('UPDATE t SET x=1 RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        $this->expectException(InvalidStructure::class);
        new SqliteBody([$step]);
    }

    public function testCheckAcceptsAPlainMutationStep(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $step = (new Binder($schema))->bind('DELETE FROM t WHERE id=1');
        self::assertInstanceOf(DeleteTableStatement::class, $step);
        SqliteBody::check($step);
        self::assertSame([$step], (new SqliteBody([$step]))->steps);
    }

    public function testCheckRejectsAStepFromAnotherDialect(): void
    {
        $step = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        SqliteBody::check($step);
    }

    #[TestWith(['UPDATE t SET x=1 RETURNING id'])]
    #[TestWith(['WITH c AS (SELECT 1 AS n) DELETE FROM t WHERE id IN (SELECT n FROM c)'])]
    #[TestWith(['CREATE TABLE u(id INTEGER)'])]
    public function testCheckRejectsOperationsThatCannotBeTriggerSteps(string $sql): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $step = (new Binder($schema))->bind($sql);
        $this->expectException(InvalidStructure::class);
        SqliteBody::check($step);
    }

    #[TestWith(['INSERT INTO t VALUES (1, 2)'])]
    #[TestWith(['INSERT INTO t SELECT 1, 2'])]
    #[TestWith(['UPDATE t SET x = u.id FROM u'])]
    #[TestWith(['UPDATE t SET x = 1'])]
    public function testCheckAcceptsEveryMutationStepForm(string $sql): void
    {
        $step = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER); CREATE TABLE u(id INTEGER)')))->bind($sql);
        $this->expectNotToPerformAssertions();
        SqliteBody::check($step);
    }

    public function testCheckRejectsAStatementThatIsNoMutation(): void
    {
        $step = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA x');
        $this->expectException(InvalidStructure::class);
        SqliteBody::check($step);
    }
}
