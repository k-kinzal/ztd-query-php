<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalAssignment;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LocalAssignment::class)]
#[Medium]
final class LocalAssignmentTest extends TestCase
{
    public function testAssignsAParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(INOUT a INT) SET a = a * 2');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        $assignment = $statement->body->assignments[0];
        self::assertInstanceOf(LocalAssignment::class, $assignment);
        self::assertSame('a', $assignment->target->variable->name);
        self::assertSame('CREATE PROCEDURE `p`(INOUT `a` integer) SET `a` = (`a` * 2)', $statement->toString());
    }

    public function testRejectsAnotherDialectValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) SET a = 1');
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        self::assertInstanceOf(LocalAssignment::class, $statement->body->assignments[0]);
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new LocalAssignment($statement->body->assignments[0]->target, $query->resultColumns()[0]->expression);
    }

    public function testDiagnosesDefault(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) SET a = DEFAULT', strict: false);
    }
}
