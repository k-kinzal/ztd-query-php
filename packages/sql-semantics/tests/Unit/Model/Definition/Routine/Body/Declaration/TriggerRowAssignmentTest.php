<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\TriggerRowAssignment;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateTriggerStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerRowAssignment::class)]
#[Medium]
final class TriggerRowAssignmentTest extends TestCase
{
    public function testAssignsANewColumnFromTheOldRow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT, m INT)'));
        $statement = $binder->bind('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.n = OLD.m');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        $assignment = $statement->body->assignments[0];
        self::assertInstanceOf(TriggerRowAssignment::class, $assignment);
        self::assertInstanceOf(TriggerColumn::class, $assignment->value);
        self::assertSame('CREATE TRIGGER `tr` BEFORE UPDATE ON `t` FOR EACH ROW SET `new`.`n` = `old`.`m`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnOldTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.n = OLD.n');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        $assignment = $statement->body->assignments[0];
        self::assertInstanceOf(TriggerRowAssignment::class, $assignment);
        self::assertInstanceOf(TriggerColumn::class, $assignment->value);
        $this->expectException(InvalidStructure::class);
        new TriggerRowAssignment($assignment->value, $assignment->value);
    }

    public function testKeepsAnUnresolvedNewColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TRIGGER tr BEFORE INSERT ON missing FOR EACH ROW SET NEW.n = 1', strict: false);
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        $assignment = $statement->body->assignments[0];
        self::assertInstanceOf(TriggerRowAssignment::class, $assignment);
        self::assertInstanceOf(UnresolvedColumnReference::class, $assignment->target);
    }

    public function testDiagnosesAnAssignmentAfterTheEvent(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.n = 1', strict: false);
    }
}
