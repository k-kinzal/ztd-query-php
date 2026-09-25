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
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionClass;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerAction;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\NamedCondition;
use SqlSemantics\Model\Definition\Routine\Body\LeaveStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(HandlerDeclaration::class)]
#[Medium]
final class HandlerDeclarationTest extends TestCase
{
    public function testBindsEveryConditionForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR 1146; DECLARE CONTINUE HANDLER FOR 1062, SQLSTATE '23000', gone, SQLWARNING, NOT FOUND, SQLEXCEPTION BEGIN END; END");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $handler = $statement->body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        self::assertSame(HandlerAction::Continue, $handler->action);
        self::assertSame(['error:1062', 'state:23000', 'name:gone', 'class:SQLWARNING', 'class:NOT FOUND', 'class:SQLEXCEPTION'], array_map(HandlerDeclaration::key(...), $handler->conditions));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testKeyComparesNamedConditionsIgnoringCase(): void
    {
        self::assertSame(HandlerDeclaration::key(new NamedCondition('Gone')), HandlerDeclaration::key(new NamedCondition('gone')));
        self::assertSame('error:16', HandlerDeclaration::key(new ErrorCode('0x10')));
        self::assertSame('state:45000', HandlerDeclaration::key(new SqlState('45000')));
        self::assertSame('class:NOT FOUND', HandlerDeclaration::key(ConditionClass::NotFound));
    }

    public function testRejectsARepeatedCondition(): void
    {
        $this->expectException(InvalidStructure::class);
        new HandlerDeclaration(HandlerAction::Exit, [new ErrorCode('1062'), new ErrorCode('0x426')], new LeaveStatement('l'));
    }

    public function testDiagnosesAConditionHandledTwiceInABlock(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE dup CONDITION FOR 1062; DECLARE EXIT HANDLER FOR dup BEGIN END; DECLARE CONTINUE HANDLER FOR 1062 BEGIN END; END', strict: false);
    }
}
