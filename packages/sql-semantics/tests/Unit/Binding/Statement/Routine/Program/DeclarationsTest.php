<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Declarations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\CursorDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\VariableDeclaration;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Declarations::class)]
#[Medium]
final class DeclarationsTest extends TestCase
{
    public function testBindClassifiesEachDeclarationAndItsScope(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p(k INT) BEGIN DECLARE a INT DEFAULT k; DECLARE gone CONDITION FOR 1146; DECLARE c CURSOR FOR SELECT n FROM t WHERE n = a; DECLARE EXIT HANDLER FOR gone SET a = 0; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        [$variable, $condition, $cursor, $handler] = $statement->body->declarations;
        self::assertInstanceOf(VariableDeclaration::class, $variable);
        self::assertInstanceOf(LocalVariableReference::class, $variable->default);
        self::assertInstanceOf(ConditionDeclaration::class, $condition);
        self::assertInstanceOf(CursorDeclaration::class, $cursor);
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindReadsLowerCaseDeclarations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)')))->bind("create procedure p() begin declare x, y int default 1; declare e condition for sqlstate '45000'; declare c cursor for select a from t; declare continue handler for e set x = 2; end");
        self::assertSame("CREATE PROCEDURE `p`() BEGIN DECLARE `x`, `y` integer DEFAULT 1; DECLARE `e` CONDITION FOR SQLSTATE '45000'; DECLARE `c` CURSOR FOR SELECT `a` AS `a` FROM `t`; DECLARE CONTINUE HANDLER FOR `e` SET `x` = 2; END", $statement->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['create procedure p() begin declare x, X int; end'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['create procedure p() begin declare x, x int; end'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['create procedure p() begin declare c cursor for select a into @v from t; end'])]
    public function testBindRejectsRepeatedNamesAndCursorsWithInto(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)')))->bind($sql);
    }
}
