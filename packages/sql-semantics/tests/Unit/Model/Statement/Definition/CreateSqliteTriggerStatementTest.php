<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\UpdatedColumns;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateSqliteTriggerStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CreateSqliteTriggerStatementTest extends TestCase
{
    public function testBindsTheEventAndRowImages(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        self::assertInstanceOf(UpdatedColumns::class, $statement->event);
        self::assertSame(['id'], $statement->event->columns);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        self::assertInstanceOf(ScalarAssignment::class, $step->writes[0]);
        self::assertInstanceOf(TriggerColumn::class, $step->writes[0]->value);
        self::assertSame(RowVersion::New, $step->writes[0]->value->version);
        self::assertSame('id', $step->writes[0]->value->columnBinding()->column->name);
        self::assertInstanceOf(BinaryExpression::class, $step->where);
        self::assertInstanceOf(TriggerColumn::class, $step->where->right);
        self::assertSame(RowVersion::Old, $step->where->right->version);
        self::assertSame('t', $step->where->right->columnBinding()->table->name);
    }

    public function testWithWhenKeepsTheOriginalAndTheProgram(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $binder = new Binder($schema);
        $statement = $binder->bind('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT new.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $changed = $statement->withWhen(Expression::literal(true, Dialect::Sqlite));
        self::assertNull($statement->when);
        self::assertNotNull($changed->when);
        self::assertSame('TRUE', $changed->when->spelling());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement->body->steps[0]), (new \SqlSemantics\SimpleSerializer())->serialize($changed->body->steps[0]));
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($changed)));
    }

    public function testWithOriginKeepsTheTriggerProgram(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $statement = $binder->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::Sqlite, [], $statement->origin->context));
        self::assertSame('other', $changed->scopeId);
        self::assertSame($statement->body, $changed->body);
        self::assertSame($statement->subject, $changed->subject);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithBodyReplacesTheProgramAndKeepsTheOriginal(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $statement = $binder->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $query = $binder->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        $delete = $binder->bind('DELETE FROM t WHERE x IS NULL');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $delete);
        $changed = $statement->withBody(new \SqlSemantics\Model\Trigger\SqliteBody([$query, $delete]));
        self::assertSame('CREATE TRIGGER "tr" AFTER UPDATE OF "id" ON "main"."t" FOR EACH ROW BEGIN SELECT 1; DELETE FROM "t" WHERE ("x" IS NULL); END', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertCount(2, $changed->body->steps);
        self::assertCount(1, $statement->body->steps);
        self::assertInstanceOf(UpdateTableStatement::class, $statement->body->steps[0]);
    }
}
