<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(TriggerColumn::class)]
#[Medium]
final class TriggerColumnTest extends TestCase
{
    public function testColumnBindingResolvesTheTriggerTableColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        self::assertInstanceOf(ScalarAssignment::class, $step->writes[0]);
        $value = $step->writes[0]->value;
        self::assertInstanceOf(TriggerColumn::class, $value);
        self::assertSame($value->binding, $value->columnBinding());
        self::assertSame('id', $value->binding->column->name);
        self::assertSame('integer', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }

    public function testReferencePartsPrefixTheRowVersion(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        self::assertInstanceOf(ScalarAssignment::class, $step->writes[0]);
        $written = $step->writes[0]->value;
        self::assertInstanceOf(TriggerColumn::class, $written);
        self::assertSame(RowVersion::New, $written->version);
        self::assertSame(['new', 'id'], $written->referenceParts());
        $where = $step->where;
        self::assertInstanceOf(BinaryExpression::class, $where);
        $compared = $where->right;
        self::assertInstanceOf(TriggerColumn::class, $compared);
        self::assertSame(RowVersion::Old, $compared->version);
        self::assertSame(['old', 'id'], $compared->referenceParts());
        self::assertStringContainsString('SET "x" = "new"."id" WHERE ("id" = "old"."id")', $statement->toString());
    }

    public function testInputsHasNoOperandsButContributesItsBindingToLineage(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        self::assertInstanceOf(ScalarAssignment::class, $step->writes[0]);
        $value = $step->writes[0]->value;
        self::assertInstanceOf(TriggerColumn::class, $value);
        self::assertSame([], $value->inputs());
        self::assertSame([$value->binding], $value->lineage());
    }

    public function testSpellingIsNullBecauseTheReferenceIsResolved(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        self::assertInstanceOf(ScalarAssignment::class, $step->writes[0]);
        $value = $step->writes[0]->value;
        self::assertInstanceOf(TriggerColumn::class, $value);
        self::assertNull($value->spelling());
        self::assertSame('"new"."id"', $value->structure()->toString());
    }

    public function testWithFactsKeepsTheBindingAndVersion(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(UpdateTableStatement::class, $step);
        self::assertInstanceOf(ScalarAssignment::class, $step->writes[0]);
        $value = $step->writes[0]->value;
        self::assertInstanceOf(TriggerColumn::class, $value);
        $copy = $value->withFacts(new ExpressionFacts($value->type, Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame($value->binding, $copy->binding);
        self::assertSame(RowVersion::New, $copy->version);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }
}
