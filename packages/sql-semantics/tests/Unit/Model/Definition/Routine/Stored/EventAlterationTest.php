<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\Definition\Routine\Stored\EventAlteration;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\AlterEventStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventAlteration::class)]
#[Medium]
final class EventAlterationTest extends TestCase
{
    public function testLeavesOmittedPropertiesUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER EVENT e COMMENT 'x'");
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertNull($statement->changes->schedule);
        self::assertNull($statement->changes->completion);
        self::assertNull($statement->changes->status);
        self::assertNull($statement->changes->body);
        self::assertSame("'x'", $statement->changes->comment?->text);
    }

    public function testRequiresAChange(): void
    {
        $this->expectException(InvalidStructure::class);
        new EventAlteration();
    }

    public function testRejectsAThreePartName(): void
    {
        $this->expectException(InvalidStructure::class);
        new EventAlteration(newName: new QualifiedName(['a', 'b', 'c']), status: EventStatus::Enabled);
    }

    public function testRejectsReturnInTheBody(): void
    {
        $function = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, $function);
        self::assertInstanceOf(ReturnStatement::class, $function->body);
        $this->expectException(InvalidStructure::class);
        new EventAlteration(body: $function->body);
    }

    public function testDiagnosesAnAlterationWithoutChanges(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER DEFINER = 'a'@'%' EVENT e", strict: false);
    }
}
