<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Trigger\TriggerSteps;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerSteps::class)]
#[Medium]
final class TriggerStepsTest extends TestCase
{
    public function testBindClassifiesEachStepWithTheNativeStatementForms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('CREATE TRIGGER tr AFTER UPDATE ON t BEGIN INSERT INTO u VALUES (NEW.a); UPDATE t SET a = 1; DELETE FROM u; SELECT 1; INSERT INTO u SELECT a FROM t; END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $statement);
        $steps = $statement->body->steps;
        self::assertCount(5, $steps);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $steps[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $steps[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $steps[2]);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $steps[3]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $steps[4]);
        self::assertSame('CREATE TRIGGER "tr" AFTER UPDATE ON "main"."t" FOR EACH ROW BEGIN INSERT INTO "u" VALUES ("new"."a"); UPDATE "t" SET "a" = 1; DELETE FROM "u"; SELECT 1; INSERT INTO "u" SELECT "a" AS "a" FROM "main"."t"; END', $statement->toString());
    }

    public function testStepRejectsAQualifiedMutationTarget(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::TriggerQualifiedTarget->message());
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('CREATE TRIGGER tr INSTEAD OF INSERT ON t FOR EACH ROW BEGIN INSERT INTO main.u VALUES (1); END');
    }

    public function testStepBindsMutationsAgainstTheRowImagesOfTheTrigger(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('CREATE TRIGGER tr AFTER DELETE ON t BEGIN DELETE FROM u WHERE a = OLD.a; END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $statement);
        $delete = $statement->body->steps[0];
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $delete);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $delete->where);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\TriggerColumn::class, $delete->where->right);
        self::assertSame(\SqlSemantics\Model\Trigger\RowVersion::Old, $delete->where->right->version);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['create trigger tr after insert on t begin insert into u values (new.a); update u set a = 1 where a = new.a; delete from u where a = 0; select 1; end', 'CREATE TRIGGER "tr" AFTER INSERT ON "main"."t" FOR EACH ROW BEGIN INSERT INTO "u" VALUES ("new"."a"); UPDATE "u" SET "a" = 1 WHERE ("a" = "new"."a"); DELETE FROM "u" WHERE ("a" = 0); SELECT 1; END'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['create trigger tr after insert on t begin insert into u select a from t; update u set a = t.a from t; end', 'CREATE TRIGGER "tr" AFTER INSERT ON "main"."t" FOR EACH ROW BEGIN INSERT INTO "u" SELECT "a" AS "a" FROM "main"."t"; UPDATE "u" SET "a" = "t"."a" FROM "main"."t"; END'])]
    public function testBindKeepsEveryStepForm(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)')))->bind($sql)->toString());
    }

    public function testStepRejectsAnIndexHint(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::TriggerIndexHint->message());
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)')))->bind('create trigger tr after insert on t begin update u indexed by i set a = 1; end');
    }

    public function testStepBindsOneParsedStep(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)');
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite), 'main'));
        $step = (new \SqlSemantics\Ast\DialectParser(Dialect::Sqlite))->parse('create trigger tr after insert on t begin delete from u where a = 0; end')->find('trigger_cmd')[0];
        $bound = TriggerSteps::step($step, $context, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite), queries: $context));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $bound);
    }
}
