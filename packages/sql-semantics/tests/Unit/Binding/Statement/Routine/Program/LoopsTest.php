<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Loops;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\LoopStatement;
use SqlSemantics\Model\Definition\Routine\Body\RepeatStatement;
use SqlSemantics\Model\Definition\Routine\Body\WhileStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Loops::class)]
#[Medium]
final class LoopsTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[TestWith(['LOOP LEAVE l; END LOOP', LoopStatement::class])]
    #[TestWith(['WHILE 1 DO LEAVE l; END WHILE', WhileStatement::class])]
    #[TestWith(['REPEAT LEAVE l; UNTIL 1 END REPEAT', RepeatStatement::class])]
    public function testBindClassifiesEachLoop(string $loop, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() l: ' . $loop);
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf($class, $statement->body);
    }

    #[TestWith(['CREATE PROCEDURE p() l: LOOP LEAVE l; END LOOP m'])]
    #[TestWith(['CREATE PROCEDURE p() l: BEGIN l: LOOP LEAVE l; END LOOP; END'])]
    public function testLabelDiagnosesMismatchedAndReusedLabels(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramLabel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
    }

    public function testLabelIgnoresCase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() Spin: LOOP LEAVE SPIN; END LOOP spin');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(LoopStatement::class, $statement->body);
        self::assertSame('Spin', $statement->body->label);
    }

    #[TestWith(['CREATE PROCEDURE p() b: BEGIN ITERATE b; END'])]
    #[TestWith(['CREATE PROCEDURE p() l: LOOP BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION LEAVE l; END; END LOOP'])]
    public function testJumpDiagnosesTargetsOutsideTheScope(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramLabel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
    }

    #[TestWith(['CREATE PROCEDURE p() BEGIN a: while 1 do leave a; end while a; END', 'CREATE PROCEDURE `p`() BEGIN `a` : WHILE 1 DO LEAVE `a`; END WHILE `a`; END'])]
    #[TestWith(['CREATE PROCEDURE p() BEGIN a: repeat iterate a; until 1 end repeat; END', 'CREATE PROCEDURE `p`() BEGIN `a` : REPEAT ITERATE `a`; UNTIL 1 END REPEAT `a`; END'])]
    #[TestWith(['CREATE PROCEDURE p() BEGIN a: LOOP LEAVE a; END LOOP A; END', 'CREATE PROCEDURE `p`() BEGIN `a` : LOOP LEAVE `a`; END LOOP `a`; END'])]
    #[TestWith(['CREATE PROCEDURE p() BEGIN a: LOOP BEGIN ITERATE a; END; END LOOP; END', 'CREATE PROCEDURE `p`() BEGIN `a` : LOOP BEGIN ITERATE `a`; END; END LOOP `a`; END'])]
    #[TestWith(['CREATE PROCEDURE p() b: BEGIN leave b; END', 'CREATE PROCEDURE `p`() `b` : BEGIN LEAVE `b`; END `b`'])]
    public function testBindSpellsLabeledLoopsAndJumps(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql)));
    }

    #[TestWith(['CREATE PROCEDURE p() b: BEGIN iterate b; END'])]
    #[TestWith(['CREATE PROCEDURE p() a: BEGIN A: LOOP LEAVE a; END LOOP; END'])]
    #[TestWith(['CREATE PROCEDURE p() BEGIN a: LOOP LEAVE a; END LOOP b; END'])]
    public function testBindRejectsAMisusedLabel(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramLabel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }
}
