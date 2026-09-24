<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConditionDeclaration::class)]
#[Medium]
final class ConditionDeclarationTest extends TestCase
{
    public function testNamesErrorNumbersAndSqlStates(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE PROCEDURE p() BEGIN DECLARE dup CONDITION FOR 1062; DECLARE gone CONDITION FOR SQLSTATE VALUE '42S02'; END");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(ConditionDeclaration::class, $statement->body->declarations[0]);
        self::assertInstanceOf(ErrorCode::class, $statement->body->declarations[0]->value);
        self::assertInstanceOf(ConditionDeclaration::class, $statement->body->declarations[1]);
        self::assertInstanceOf(SqlState::class, $statement->body->declarations[1]->value);
        self::assertSame("CREATE PROCEDURE `p`() BEGIN DECLARE `dup` CONDITION FOR 1062; DECLARE `gone` CONDITION FOR SQLSTATE '42S02'; END", $statement->toString());
    }

    public function testRequiresAName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ConditionDeclaration('', new ErrorCode('1'));
    }

    public function testDiagnosesACompletionSqlState(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE PROCEDURE p() BEGIN DECLARE ok CONDITION FOR SQLSTATE '00000'; END", strict: false);
    }
}
