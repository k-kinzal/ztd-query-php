<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\ControlFlow;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\IfStatement;
use SqlSemantics\Model\Definition\Routine\Body\SearchedCaseStatement;
use SqlSemantics\Model\Definition\Routine\Body\SimpleCaseStatement;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ControlFlow::class)]
#[Medium]
final class ControlFlowTest extends TestCase
{
    public function testIfFlattensElseIfChains(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a = 1 THEN DO 1; ELSEIF a = 2 THEN DO 2; ELSEIF a = 3 THEN DO 3; END IF');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(IfStatement::class, $statement->body);
        self::assertCount(3, $statement->body->branches);
        self::assertSame([], $statement->body->otherwise);
    }

    public function testCaseDistinguishesSimpleAndSearchedForms(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $simple = $binder->bind('CREATE PROCEDURE p(a INT) CASE a WHEN 1 THEN DO 1; ELSE DO 0; END CASE');
        $searched = $binder->bind('CREATE PROCEDURE p(a INT) CASE WHEN a = 1 THEN DO 1; END CASE');
        self::assertInstanceOf(CreateProcedureStatement::class, $simple);
        self::assertInstanceOf(CreateProcedureStatement::class, $searched);
        self::assertInstanceOf(SimpleCaseStatement::class, $simple->body);
        self::assertInstanceOf(LocalVariableReference::class, $simple->body->operand);
        self::assertInstanceOf(SearchedCaseStatement::class, $searched->body);
    }

    public function testBranchBindsTheGuardWithLocalVariables(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a THEN DO 1; END IF');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(IfStatement::class, $statement->body);
        self::assertInstanceOf(LocalVariableReference::class, $statement->body->branches[0]->condition);
    }
}
