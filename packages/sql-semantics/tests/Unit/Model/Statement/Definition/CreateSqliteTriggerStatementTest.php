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
        self::assertSame($statement->body->steps[0]->toString(), $changed->body->steps[0]->toString());
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $binder->bind($changed->toString()));
    }
}
