<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\HandlerConditions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionClass;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(HandlerConditions::class)]
#[Medium]
final class HandlerConditionsTest extends TestCase
{
    public function testListKeepsConditionOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING BEGIN END; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(HandlerDeclaration::class, $statement->body->declarations[0]);
        self::assertSame([ConditionClass::SqlException, ConditionClass::SqlWarning], $statement->body->declarations[0]->conditions);
    }

    #[TestWith(['DECLARE EXIT HANDLER FOR NOT FOUND, NOT FOUND BEGIN END'])]
    #[TestWith(['DECLARE EXIT HANDLER FOR 1062 BEGIN END; DECLARE CONTINUE HANDLER FOR 0x426 BEGIN END'])]
    public function testListDiagnosesAConditionHandledTwice(string $declarations): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN ' . $declarations . '; END', strict: false);
    }

    public function testConditionDiagnosesAnUndeclaredName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR missing BEGIN END; END', strict: false);
    }

    #[TestWith(["SQLSTATE 'bad'"])]
    #[TestWith(["SQLSTATE '00000'"])]
    #[TestWith(['0'])]
    public function testValueDiagnosesInvalidConditionValues(string $value): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR ' . $value . '; END', strict: false);
    }
}
