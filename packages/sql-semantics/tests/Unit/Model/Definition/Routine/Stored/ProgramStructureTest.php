<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorOpenStatement;
use SqlSemantics\Model\Definition\Routine\Body\IterateStatement;
use SqlSemantics\Model\Definition\Routine\Body\LeaveStatement;
use SqlSemantics\Model\Definition\Routine\Body\LoopStatement;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramStructure;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProgramStructure::class)]
#[Medium]
final class ProgramStructureTest extends TestCase
{
    public function testCheckRequiresReturnInAFunction(): void
    {
        $this->expectException(InvalidStructure::class);
        ProgramStructure::check(new BlockStatement(null), ProgramKind::Function);
    }

    public function testCheckRejectsReturnOutsideAFunction(): void
    {
        $function = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $function);
        self::assertInstanceOf(ReturnStatement::class, $function->body);
        $this->expectException(InvalidStructure::class);
        ProgramStructure::check($function->body, ProgramKind::Trigger);
    }

    public function testCheckRejectsIterateOnABlock(): void
    {
        $this->expectException(InvalidStructure::class);
        ProgramStructure::check(new BlockStatement('b', [], [new IterateStatement('b')]), ProgramKind::Procedure);
    }

    public function testCheckRejectsAReusedEnclosingLabel(): void
    {
        $this->expectException(InvalidStructure::class);
        ProgramStructure::check(new BlockStatement('b', [], [new LoopStatement('B', [new LeaveStatement('b')])]), ProgramKind::Procedure);
    }

    public function testCheckRejectsAnUndeclaredCursor(): void
    {
        $this->expectException(InvalidStructure::class);
        ProgramStructure::check(new BlockStatement(null, [], [new CursorOpenStatement('c')]), ProgramKind::Event);
    }

    public function testWalkCountsReturnsIncludingHandlerBodies(): void
    {
        $function = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RETURN 0; RETURN 1; END');
        self::assertInstanceOf(CreateFunctionStatement::class, $function);
        self::assertInstanceOf(BlockStatement::class, $function->body);
        self::assertSame(2, ProgramStructure::walk($function->body, ProgramKind::Function, [], []));
    }

    public function testLabelsRecordsLoopsAndBlocks(): void
    {
        self::assertSame(['b' => false], ProgramStructure::labels(new BlockStatement('B'), []));
        self::assertSame(['l' => true], ProgramStructure::labels(new LoopStatement('l', [new LeaveStatement('l')]), []));
        self::assertSame(['x' => true], ProgramStructure::labels(new LeaveStatement('y'), ['x' => true]));
    }

    public function testReferencesAcceptsADeclaredCursor(): void
    {
        ProgramStructure::references(new CursorOpenStatement('C'), ProgramKind::Procedure, [], ['cursor:c' => true]);
        $this->expectException(InvalidStructure::class);
        ProgramStructure::references(new IterateStatement('l'), ProgramKind::Procedure, ['l' => false], []);
    }

    public function testHandlerRejectsAnUndeclaredCondition(): void
    {
        $handler = new \SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration(\SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerAction::Exit, [new \SqlSemantics\Model\Definition\Routine\Body\Declaration\NamedCondition('gone')], new BlockStatement(null));
        self::assertSame(0, ProgramStructure::handler($handler, ProgramKind::Procedure, ['condition:gone' => true]));
        $this->expectException(InvalidStructure::class);
        ProgramStructure::handler($handler, ProgramKind::Procedure, []);
    }

    public function testChildrenListsTheNestedStatementsOfEveryBranch(): void
    {
        $procedure = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a THEN DO 1; ELSEIF a > 1 THEN DO 2; DO 3; ELSE DO 4; END IF');
        self::assertInstanceOf(CreateProcedureStatement::class, $procedure);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Routine\Body\ProgramStatement::class, $procedure->body);
        self::assertCount(4, ProgramStructure::children($procedure->body));
        self::assertSame([], ProgramStructure::children(new LeaveStatement('x')));
    }
}
