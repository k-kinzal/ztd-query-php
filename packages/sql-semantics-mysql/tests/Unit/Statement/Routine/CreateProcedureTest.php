<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreateProcedure::class)]
#[Medium]
final class CreateProcedureTest extends TestCase
{
    public function testDeriveStatementResolvesParametersAndLocalVariablesInEveryBodyStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE PROCEDURE p(x INT, OUT y INT) BEGIN DECLARE v INT DEFAULT x; '
            . 'UPDATE t SET b = x WHERE a = v; INSERT INTO t (a, b) VALUES (x, v); DELETE FROM t WHERE b = y; '
            . 'SET @w = x + v; SELECT a INTO y FROM t WHERE a = x LIMIT 1; SELECT x, v, a FROM t; END', [$table]);
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveStatementReportsAnUnknownNameInACompleteContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE PROCEDURE p(x INT) UPDATE t SET b = x + zz', [$table]);

        self::assertSame(['Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementEndsTheScopeOfALocalVariableWithItsBlock(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE PROCEDURE p() BEGIN BEGIN DECLARE z INT; SET @in = z; END; SELECT z; END', [$table]);

        self::assertSame(['Column z does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementLetsALocalVariableHideAParameter(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE PROCEDURE p(x INT) BEGIN DECLARE x INT DEFAULT x; SET @v = x; END', [$table]);

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveStatementReportsADuplicateParameterWhateverItsCase(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT, OUT X INT) SELECT 1');
        $problem = $create->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $create->facts->diagnostics);
        self::assertSame(ProgramRule::DuplicateParameter, $problem->rule);
        self::assertSame('Duplicate parameter: X', $problem->message());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDeriveStatementReportsAStatementAStoredProgramMayNotContain(): iterable
    {
        yield 'LOCK TABLES' => ['LOCK TABLES t READ', 'LOCK is not allowed in stored procedures'];
        yield 'UNLOCK TABLES' => ['UNLOCK TABLES', 'UNLOCK is not allowed in stored procedures'];
        yield 'USE' => ['USE shop', 'USE is not allowed in stored procedures'];
        yield 'LOAD DATA' => ["LOAD DATA INFILE 'f' INTO TABLE t", 'LOAD DATA is not allowed in stored procedures'];
        yield 'LOAD XML' => ["LOAD XML INFILE 'f' INTO TABLE t", 'LOAD XML is not allowed in stored procedures'];
        yield 'ALTER VIEW' => ['ALTER VIEW v AS SELECT 1', 'ALTER VIEW is not allowed in stored procedures'];
        yield 'RETURN' => ['RETURN 1', 'RETURN is only allowed in a FUNCTION'];
        yield 'CREATE PROCEDURE' => ['CREATE PROCEDURE q() SELECT 1', "Can't create a PROCEDURE from within another stored routine"];
        yield 'CREATE FUNCTION' => ['CREATE FUNCTION g() RETURNS INT RETURN 1', "Can't create a FUNCTION from within another stored routine"];
        yield 'CREATE TRIGGER' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a = 1', "Can't create a TRIGGER from within another stored routine"];
        yield 'DROP PROCEDURE' => ['DROP PROCEDURE q', "Can't drop or alter a PROCEDURE from within another stored routine"];
        yield 'ALTER FUNCTION' => ["ALTER FUNCTION g COMMENT 'x'", "Can't drop or alter a FUNCTION from within another stored routine"];
        yield 'CREATE EVENT' => ['CREATE EVENT e ON SCHEDULE AT NOW() DO SELECT 1', 'Recursion of EVENT DDL statements is forbidden when body is present'];
        yield 'nested in a block' => ['BEGIN IF 1 THEN LOCK TABLES t READ; END IF; END', 'LOCK is not allowed in stored procedures'];
    }

    #[DataProvider('providerDeriveStatementReportsAStatementAStoredProgramMayNotContain')]
    public function testDeriveStatementReportsAStatementAStoredProgramMayNotContain(string $body, string $message): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ' . $body);

        self::assertSame([$message], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeriveStatementAcceptsWhatOnlyAProcedureMayContain(): iterable
    {
        yield 'result set' => ['SELECT 1'];
        yield 'SHOW' => ['SHOW TABLES'];
        yield 'commit' => ['COMMIT'];
        yield 'dynamic SQL' => ['PREPARE s FROM @q'];
        yield 'DROP TRIGGER' => ['DROP TRIGGER tr'];
        yield 'ALTER EVENT without a body' => ['ALTER EVENT e ENABLE'];
    }

    #[DataProvider('providerDeriveStatementAcceptsWhatOnlyAProcedureMayContain')]
    public function testDeriveStatementAcceptsWhatOnlyAProcedureMayContain(string $body): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ' . $body)->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDefinition(): iterable
    {
        yield 'MySQL 5.6 characteristics' => [
            'mysql-5.6.51',
            "create definer=root@localhost procedure shop.p(in a int, out b varchar(10), inout c int) comment 'x' language sql not deterministic contains sql sql security invoker begin end",
            "CREATE DEFINER = root@localhost PROCEDURE shop.p(IN a INT, OUT b VARCHAR(10), INOUT c INT) COMMENT 'x' LANGUAGE SQL NOT DETERMINISTIC CONTAINS SQL SQL SECURITY INVOKER BEGIN END",
        ];
        yield 'MySQL 5.7 program body' => [
            'mysql-5.7.44',
            'create procedure p() begin declare c cursor for select 1; open c; close c; end',
            'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; OPEN c; CLOSE c; END',
        ];
        yield 'MySQL 8.0 IF NOT EXISTS' => [
            'mysql-8.0.44',
            "create definer = 'admin'@'%' procedure if not exists shop.p(out total decimal(10,2)) modifies sql data set total = 1",
            'CREATE DEFINER = `admin`@`%` PROCEDURE IF NOT EXISTS shop.p(OUT total DECIMAL(10, 2)) MODIFIES SQL DATA SET total = 1',
        ];
        yield 'MySQL 9.1 external body' => [
            'mysql-9.1.0',
            'create procedure p(in a int) language javascript as $$ return a $$',
            'CREATE PROCEDURE p(IN a INT) LANGUAGE javascript AS $$ return a $$',
        ];
    }

    #[DataProvider('providerRenderWritesTheDefinition')]
    public function testRenderWritesTheDefinition(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('A routine name has at most a database qualifier.');

        new CreateProcedure(new QualifiedName(new Name('p'), new Name('shop'), new Name('def')), new ParameterList([]), new Block([]));
    }

    public function testABodyOfAnotherClassIsRejected(): void
    {
        $this->expectExceptionMessage('A member of a stored program is a program statement or an SQL statement.');

        new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new Parameter(new Name('x'), new Integral(IntegralKind::Int)));
    }
}
