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
}
