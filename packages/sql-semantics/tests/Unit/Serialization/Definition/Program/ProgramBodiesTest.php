<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\IfStatement;
use SqlSemantics\Model\Definition\Routine\Body\LeaveStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Program\ProgramBodies;

#[CoversClass(ProgramBodies::class)]
#[Medium]
final class ProgramBodiesTest extends TestCase
{
    public function testWriteProducesARebindableBody(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p() main: BEGIN DECLARE done INT DEFAULT 0; DECLARE v INT; DECLARE c CURSOR FOR SELECT n FROM t; DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1; OPEN c; scan: LOOP FETCH c INTO v; IF done THEN LEAVE scan; END IF; ITERATE scan; END LOOP; CLOSE c; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame('`main` : BEGIN DECLARE `done` integer DEFAULT 0; DECLARE `v` integer; DECLARE `c` CURSOR FOR SELECT `n` AS `n` FROM `t`; DECLARE CONTINUE HANDLER FOR NOT FOUND SET `done` = 1; OPEN `c`; `scan` : LOOP FETCH `c` INTO `v`; IF `done` THEN LEAVE `scan`; END IF; ITERATE `scan`; END LOOP `scan`; CLOSE `c`; END `main`', ProgramBodies::write($statement->body)->toString());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testStatementsTerminatesEachStatement(): void
    {
        self::assertSame('LEAVE `a`; LEAVE `b`;', ProgramBodies::statements([new LeaveStatement('a'), new LeaveStatement('b')])->toString());
    }

    public function testTerminatedAppendsASemicolon(): void
    {
        self::assertSame('END;', ProgramBodies::terminated(Build::keyword('END'))->toString());
    }

    public function testLabeledRepeatsTheLabelAtTheEnd(): void
    {
        self::assertSame('`x` : LOOP END LOOP `x`', ProgramBodies::labeled('x', [Build::keyword('LOOP'), Build::keyword('END LOOP')])->toString());
        self::assertSame('LOOP', ProgramBodies::labeled(null, [Build::keyword('LOOP')])->toString());
    }

    public function testBranchesUsesTheContinuationKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a THEN DO 1; ELSEIF a THEN DO 2; END IF');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(IfStatement::class, $statement->body);
        self::assertSame('IF `a` THEN DO 1; ELSEIF `a` THEN DO 2;', (new \SqlSemantics\Model\Sql\Tree('branches', ProgramBodies::branches('IF', 'ELSEIF', $statement->body->branches)))->toString());
    }

    public function testOtherwiseIsEmptyWithoutElse(): void
    {
        self::assertSame([], ProgramBodies::otherwise([]));
        self::assertSame('ELSE LEAVE `a`;', (new \SqlSemantics\Model\Sql\Tree('else', ProgramBodies::otherwise([new LeaveStatement('a')])))->toString());
    }

    public function testWriteWritesEveryControlStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p(IN a INT) BEGIN DECLARE x INT; DECLARE y INT; IF a > 1 THEN SET x = 1; SET y = 2; ELSEIF a > 0 THEN SET x = 2; ELSE SET x = 3; SET y = 4; END IF; CASE a WHEN 1 THEN SET x = 1; WHEN 2 THEN SET x = 2; ELSE SET x = 0; END CASE; CASE WHEN a > 1 THEN SET x = 1; ELSE SET x = 0; END CASE; w: WHILE x < 10 DO SET x = x + 1; END WHILE w; REPEAT SET x = x - 1; UNTIL x < 0 END REPEAT; SELECT n, n INTO x, y FROM t LIMIT 1; INSERT INTO t VALUES (x); END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame('BEGIN DECLARE `x` integer; DECLARE `y` integer; IF(`a` > 1) THEN SET `x` = 1; SET `y` = 2; ELSEIF(`a` > 0) THEN SET `x` = 2; ELSE SET `x` = 3; SET `y` = 4; END IF; CASE `a` WHEN 1 THEN SET `x` = 1; WHEN 2 THEN SET `x` = 2; ELSE SET `x` = 0; END CASE; CASE WHEN (`a` > 1) THEN SET `x` = 1; ELSE SET `x` = 0; END CASE; `w` : WHILE(`x` < 10) DO SET `x` = (`x` + 1); END WHILE `w`; REPEAT SET `x` = (`x` - 1); UNTIL(`x` < 0) END REPEAT; SELECT `n` AS `n`, `n` AS `n` FROM `t` LIMIT 1 INTO `x`, `y`; INSERT INTO `t` VALUES (`x`); END', ProgramBodies::write($statement->body)->toString());
    }

    public function testWriteWritesAReturn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT DETERMINISTIC BEGIN DECLARE x INT; RETURN a + 1; END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame('BEGIN DECLARE `x` integer; RETURN(`a` + 1); END', ProgramBodies::write($statement->body)->toString());
    }
}
