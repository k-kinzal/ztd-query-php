<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\DeclarationOrder;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\VariableDeclaration;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DeclarationOrder::class)]
#[Medium]
final class DeclarationOrderTest extends TestCase
{
    public function testCheckRejectsAVariableAfterACursor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        DeclarationOrder::check([...$statement->body->declarations, new VariableDeclaration(['x'], new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')))]);
    }

    public function testCheckRejectsARepeatedConditionName(): void
    {
        $this->expectException(InvalidStructure::class);
        DeclarationOrder::check([new ConditionDeclaration('c', new ErrorCode('1')), new ConditionDeclaration('C', new ErrorCode('2'))]);
    }

    public function testNamesSeparatesVariableAndConditionNamespaces(): void
    {
        $variable = new VariableDeclaration(['c'], new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        $condition = new ConditionDeclaration('C', new ErrorCode('1'));
        self::assertSame(['variable:c'], DeclarationOrder::names($variable));
        self::assertSame(['condition:c'], DeclarationOrder::names($condition));
        DeclarationOrder::check([$variable, $condition]);
    }

    public function testNamesFoldsCursorNamesAndLeavesHandlersUnnamed(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE a, B INT; DECLARE e CONDITION FOR 1051; DECLARE C CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR e SET a = 1; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame([['variable:a', 'variable:b'], ['condition:e'], ['cursor:c'], []], array_map(DeclarationOrder::names(...), $statement->body->declarations));
    }

    public function testCheckRejectsACursorAfterAHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        DeclarationOrder::check([$statement->body->declarations[1], $statement->body->declarations[0]]);
    }

    public function testCheckRejectsAVariableAfterAHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        DeclarationOrder::check([$statement->body->declarations[1], $statement->body->declarations[0]]);
    }

    public function testCheckRejectsARepeatedCursorNameInAnotherCase(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $lower = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; END');
        $upper = $binder->bind('CREATE PROCEDURE q() BEGIN DECLARE C CURSOR FOR SELECT 2; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $lower);
        self::assertInstanceOf(CreateProcedureStatement::class, $upper);
        self::assertInstanceOf(BlockStatement::class, $lower->body);
        self::assertInstanceOf(BlockStatement::class, $upper->body);
        $this->expectException(InvalidStructure::class);
        DeclarationOrder::check([...$lower->body->declarations, ...$upper->body->declarations]);
    }

    public function testCheckAcceptsEveryKindInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c INT; DECLARE e CONDITION FOR 1051; DECLARE C CURSOR FOR SELECT 1; DECLARE d CURSOR FOR SELECT 2; DECLARE CONTINUE HANDLER FOR e BEGIN END; DECLARE EXIT HANDLER FOR SQLWARNING BEGIN END; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertCount(6, $statement->body->declarations);
        DeclarationOrder::check($statement->body->declarations);
    }
}
