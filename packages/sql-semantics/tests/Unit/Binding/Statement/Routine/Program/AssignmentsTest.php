<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Assignments;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Assignments::class)]
#[Medium]
final class AssignmentsTest extends TestCase
{
    public function testBindLeavesASetWithoutProgramTargetsOrdinary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() SET @a = 1, sql_mode = DEFAULT');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(EmbeddedStatement::class, $statement->body);
        self::assertInstanceOf(SetStatement::class, $statement->body->statement);
    }

    public function testBindKeepsCarriedScopesOfOtherItems(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) SET GLOBAL max_connections = a, a = 2, wait_timeout = 3');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        self::assertInstanceOf(AssignedSetting::class, $statement->body->assignments[2]);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET OLD.n = 1'])]
    #[TestWith(['CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW SET NEW.n = 1'])]
    #[TestWith(['CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW SET NEW.n = 1'])]
    #[TestWith(['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.n = DEFAULT'])]
    public function testTargetDiagnosesRowsTheTriggerCannotChange(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind($sql, strict: false);
    }

    public function testTargetTreatsAnExplicitScopeAsASetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(wait_timeout INT) SET SESSION wait_timeout = 1');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(EmbeddedStatement::class, $statement->body);
    }
}
