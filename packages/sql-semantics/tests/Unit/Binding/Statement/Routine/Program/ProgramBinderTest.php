<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProgramBinder::class)]
#[Medium]
final class ProgramBinderTest extends TestCase
{
    public function testStatementBindsNestedConstructs(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO outer_block: BEGIN DECLARE i INT DEFAULT 0; l: LOOP SET i = i + 1; IF i > 3 THEN LEAVE l; END IF; END LOOP l; END outer_block');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testListKeepsStatementOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO BEGIN DO 1; DO 2; DO 3; END');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame('BEGIN DO 1; DO 2; DO 3; END', \SqlSemantics\Serialization\Definition\Program\ProgramBodies::write($statement->body)->toString());
    }

    public function testUnwrapReachesTheConstruct(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('CREATE PROCEDURE p() BEGIN END');
        $statement = Tree::outer($tree, ['sp_proc_stmt'])[0];
        self::assertSame('sp_unlabeled_block', ProgramBinder::unwrap($statement)->name);
    }

    public function testReturnBindsTheFunctionResult(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a + 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
    }

    public function testReturnIsDiagnosedInATrigger(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramStatement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RETURN 1', strict: false);
    }

    public function testBindReadsEveryFlowControlAndCursorStatement(): void
    {
        $sql = 'CREATE PROCEDURE p(x INT) b: BEGIN DECLARE v INT; DECLARE c CURSOR FOR SELECT a FROM t; OPEN c; FETCH c INTO v; CLOSE c; CASE x WHEN 1 THEN SET v = 1; ELSE SET v = 2; END CASE; l: LOOP ITERATE l; END LOOP l; WHILE x DO LEAVE b; END WHILE; REPEAT SET v = 3; UNTIL x END REPEAT; END b';
        $expected = 'CREATE PROCEDURE `p`(IN `x` integer) `b` : BEGIN DECLARE `v` integer; DECLARE `c` CURSOR FOR SELECT `a` AS `a` FROM `t`; OPEN `c`; FETCH `c` INTO `v`; CLOSE `c`; CASE `x` WHEN 1 THEN SET `v` = 1; ELSE SET `v` = 2; END CASE; `l` : LOOP ITERATE `l`; END LOOP `l`; WHILE `x` DO LEAVE `b`; END WHILE; REPEAT SET `v` = 3; UNTIL `x` END REPEAT; END `b`';
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
