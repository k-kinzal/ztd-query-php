<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Trigger\RelationTriggers;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerLevel;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Schema\CreateSchemaStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateConstraintTriggerStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateTriggerStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationTriggers::class)]
#[Medium]
final class RelationTriggersTest extends TestCase
{
    public function testBindReadsEveryClauseOfATrigger(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('CREATE OR REPLACE TRIGGER audit AFTER UPDATE ON t REFERENCING NEW TABLE AS added OLD TABLE removed FOR EACH STATEMENT EXECUTE PROCEDURE audit.log(1, \'x\')');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(Timing::After, $statement->timing);
        self::assertSame([], $statement->events->columns);
        self::assertSame(TriggerLevel::Statement, $statement->level);
        self::assertSame([RowVersion::New, RowVersion::Old], array_map(static fn ($table) => $table->version, $statement->transitions));
        self::assertTrue($statement->orReplace);
        self::assertSame('CREATE OR REPLACE TRIGGER "audit" AFTER UPDATE ON "public"."t" REFERENCING NEW TABLE AS "added" OLD TABLE AS "removed" FOR EACH STATEMENT EXECUTE FUNCTION "audit"."log"(\'1\', \'x\')', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['CREATE TRIGGER x AFTER INSERT OR INSERT ON t EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE TRIGGER x INSTEAD OF INSERT ON t EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE TRIGGER x INSTEAD OF UPDATE OF a ON t FOR EACH ROW EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE TRIGGER x AFTER TRUNCATE ON t FOR EACH ROW EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE TRIGGER x BEFORE INSERT ON t REFERENCING NEW TABLE n EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE TRIGGER x AFTER INSERT ON t REFERENCING NEW ROW n EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE OR REPLACE CONSTRAINT TRIGGER x AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()'])]
    public function testBindDiagnosesATriggerThePostgreSqlServerRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TriggerDefinition->message());
        $binder->bind($sql);
    }

    public function testBindPlacesATriggerInsideASchemaDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA s CREATE TRIGGER k BEFORE INSERT ON t EXECUTE FUNCTION f()', strict: false);
        self::assertInstanceOf(CreateSchemaStatement::class, $statement);
        self::assertInstanceOf(CreateTriggerStatement::class, $statement->elements[0]);
    }

    #[TestWith(['DEFERRABLE INITIALLY DEFERRED', CheckingTime::DeferrableDeferred])]
    #[TestWith(['INITIALLY IMMEDIATE DEFERRABLE', CheckingTime::DeferrableImmediate])]
    #[TestWith(['NOT DEFERRABLE', CheckingTime::Immediate])]
    public function testConstraintReadsTheDeferral(string $attributes, CheckingTime $checking): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE p(a INT)'));
        $statement = $binder->bind('CREATE CONSTRAINT TRIGGER c AFTER DELETE ON t FROM p ' . $attributes . ' FOR EACH ROW EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        self::assertSame($checking, $statement->checking);
        self::assertSame(['public', 'p'], $statement->referenced?->name->parts);
    }

    #[TestWith(['NOT VALID'])]
    #[TestWith(['NO INHERIT'])]
    #[TestWith(['INITIALLY DEFERRED NOT DEFERRABLE'])]
    #[TestWith(['INITIALLY DEFERRED INITIALLY IMMEDIATE'])]
    public function testConstraintRejectsAttributesOfOtherConstraints(string $attributes): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $this->expectException(InvalidSql::class);
        $binder->bind('CREATE CONSTRAINT TRIGGER c AFTER DELETE ON t ' . $attributes . ' FOR EACH ROW EXECUTE FUNCTION f()');
    }

    public function testTableKeepsAnUnknownTableAsADiagnosedDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRIGGER x AFTER INSERT ON missing EXECUTE FUNCTION f()', strict: false);
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(['public', 'missing'], $statement->table->name->parts);
        self::assertNotSame([], $statement->diagnostics);
    }

    public function testEventsKeepTheWrittenOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER x BEFORE DELETE OR INSERT OR TRUNCATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame([TriggerEvent::Delete, TriggerEvent::Insert, TriggerEvent::Truncate], $statement->events->events);
    }

    public function testInvocationNormalizesProcedureToFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER x AFTER INSERT ON t EXECUTE PROCEDURE "S".f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(['S', 'f'], $statement->invocation->function->parts);
        self::assertStringEndsWith('EXECUTE FUNCTION "S"."f"()', $statement->toString());
    }

    #[TestWith(['42', '42'])]
    #[TestWith(['0x1F', '31'])]
    #[TestWith(['1_000', '1000'])]
    #[TestWith(['1.50', '1.50'])]
    #[TestWith(["'it''s'", "it's"])]
    #[TestWith(['Label', 'label'])]
    #[TestWith(['"Label"', 'Label'])]
    #[TestWith(['NULL', 'null'])]
    public function testArgumentPassesTheTextTheFunctionReceives(string $spelling, string $text): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER x AFTER INSERT ON t EXECUTE FUNCTION f(' . $spelling . ')');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame([$text], $statement->invocation->arguments);
    }

    public function testTransitionsReadBothRowVersions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER x AFTER UPDATE ON t REFERENCING OLD TABLE AS "Old" NEW TABLE fresh EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(['Old', 'fresh'], array_map(static fn ($table) => $table->name, $statement->transitions));
    }

    public function testConditionReadsTheRowImagesOfTheSubjectTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER x BEFORE UPDATE ON t FOR EACH ROW WHEN (OLD.a IS DISTINCT FROM NEW.a) EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $columns = array_values(array_filter(\SqlSemantics\Model\Traversal\Expressions::all($statement), static fn ($expression) => $expression instanceof TriggerColumn));
        self::assertCount(2, $columns);
    }

    public function testConditionCannotReadAnImageTheEventLacks(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER x BEFORE INSERT ON t FOR EACH ROW WHEN (OLD.a > 0) EXECUTE FUNCTION f()', strict: false);
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertNotSame([], $statement->diagnostics);
    }
}
