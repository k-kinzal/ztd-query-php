<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreateFunction::class)]
#[Medium]
final class CreateFunctionTest extends TestCase
{
    public function testDeriveStatementResolvesParametersAndLocalVariablesInEveryBodyStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE FUNCTION f(x INT) RETURNS INT BEGIN DECLARE y INT; '
            . 'SELECT a INTO y FROM t WHERE a = x LIMIT 1; UPDATE t SET b = x WHERE a = y; INSERT INTO t (a) VALUES (x); '
            . 'DELETE FROM t WHERE b = y; SET @v = x + y; RETURN x + y; END', [$table]);

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveStatementReportsAnUnknownNameInACompleteContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE FUNCTION f(x INT) RETURNS INT BEGIN UPDATE t SET b = x WHERE a = zz; RETURN x; END', [$table]);

        self::assertSame(['Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementReportsADuplicateParameter(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(x INT, x BIGINT) RETURNS INT RETURN x');
        $problem = $create->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $create->facts->diagnostics);
        self::assertSame(ProgramRule::DuplicateParameter, $problem->rule);
        self::assertSame('Duplicate parameter: x', $problem->message());
    }

    public function testDeriveStatementReportsAMissingReturn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION shop.f(x INT) RETURNS INT BEGIN SET @a = x; END');
        $problem = $create->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $create->facts->diagnostics);
        self::assertSame(ProgramRule::MissingReturn, $problem->rule);
        self::assertSame('No RETURN found in FUNCTION f', $problem->message());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeriveStatementFindsAReturnAnywhereInTheBody(): iterable
    {
        yield 'single statement' => ['RETURN x'];
        yield 'branch' => ['BEGIN IF x > 0 THEN RETURN 1; END IF; END'];
        yield 'loop' => ['BEGIN WHILE x > 0 DO RETURN x; END WHILE; END'];
        yield 'handler' => ['BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RETURN 0; SET @a = 1; END'];
    }

    #[DataProvider('providerDeriveStatementFindsAReturnAnywhereInTheBody')]
    public function testDeriveStatementFindsAReturnAnywhereInTheBody(string $body): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(x INT) RETURNS INT ' . $body)->facts->diagnostics);
    }

    public function testDeriveStatementLeavesAnExternalBodyOpaque(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('CREATE FUNCTION f(x INT) RETURNS INT LANGUAGE JAVASCRIPT AS $$ return zz $$');
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);

        self::assertInstanceOf(ExternalBody::class, $statement->body);
        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDeriveStatementReportsAStatementAStoredFunctionMayNotContain(): iterable
    {
        yield 'result set' => ['SELECT 1', 'Not allowed to return a result set from a function'];
        yield 'set operation' => ['SELECT 1 UNION SELECT 2', 'Not allowed to return a result set from a function'];
        yield 'SHOW' => ['SHOW TABLES', 'Not allowed to return a result set from a function'];
        yield 'EXPLAIN' => ['EXPLAIN SELECT 1', 'Not allowed to return a result set from a function'];
        yield 'CHECK TABLE' => ['CHECK TABLE t', 'Not allowed to return a result set from a function'];
        yield 'COMMIT' => ['COMMIT', 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'ROLLBACK' => ['ROLLBACK', 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'START TRANSACTION' => ['START TRANSACTION', 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'PREPARE' => ['PREPARE s FROM @q', 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'EXECUTE' => ['EXECUTE s', 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'DEALLOCATE PREPARE' => ['DEALLOCATE PREPARE s', 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'FLUSH' => ['FLUSH PRIVILEGES', 'FLUSH is not allowed in stored function or trigger'];
        yield 'LOCK TABLES' => ['LOCK TABLES t READ', 'LOCK is not allowed in stored procedures'];
        yield 'DROP FUNCTION' => ['DROP FUNCTION g', "Can't drop or alter a FUNCTION from within another stored routine"];
    }

    #[DataProvider('providerDeriveStatementReportsAStatementAStoredFunctionMayNotContain')]
    public function testDeriveStatementReportsAStatementAStoredFunctionMayNotContain(string $statement, string $message): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT BEGIN ' . $statement . '; RETURN 1; END');

        self::assertSame([$message], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsAQueryThatWritesIntoVariables(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1 INTO @a; (SELECT 2 INTO @b); RETURN @a; END');

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveStatementReportsAResultSetAndAMissingReturnTogether(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT SELECT 1');

        self::assertSame(
            ['Not allowed to return a result set from a function', 'No RETURN found in FUNCTION f'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics),
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDefinition(): iterable
    {
        yield 'MySQL 5.6 characteristics' => [
            'mysql-5.6.51',
            "create definer=current_user function shop.f(a char(3) charset latin1) returns varchar(5) charset utf8 deterministic reads sql data comment 'c' return a",
            "CREATE DEFINER = CURRENT_USER FUNCTION shop.f(a CHAR(3) CHARSET latin1) RETURNS VARCHAR(5) CHARSET utf8 DETERMINISTIC READS SQL DATA COMMENT 'c' RETURN a",
        ];
        yield 'MySQL 5.7 program body' => [
            'mysql-5.7.44',
            'create function f(a int) returns bigint not deterministic no sql begin declare b bigint default a * 2; return b; end',
            'CREATE FUNCTION f(a INT) RETURNS BIGINT NOT DETERMINISTIC NO SQL BEGIN DECLARE b BIGINT DEFAULT a * 2; RETURN b; END',
        ];
        yield 'MySQL 8.0 IF NOT EXISTS and COLLATE' => [
            'mysql-8.0.44',
            "create function if not exists f(a int) returns char(3) collate utf8mb4_bin no sql begin return 'x'; end",
            "CREATE FUNCTION IF NOT EXISTS f(a INT) RETURNS CHAR(3) COLLATE utf8mb4_bin NO SQL BEGIN RETURN 'x'; END",
        ];
        yield 'MySQL 9.1 external body' => [
            'mysql-9.1.0',
            "create function f() returns int language javascript as 'return 1'",
            "CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS 'return 1'",
        ];
    }

    #[DataProvider('providerRenderWritesTheDefinition')]
    public function testRenderWritesTheDefinition(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testAParameterWithADirectionIsRejected(): void
    {
        $this->expectExceptionMessage('A function parameter has no direction.');

        new CreateFunction(
            new QualifiedName(new Name('f')),
            new ParameterList([new Parameter(new Name('x'), new Integral(IntegralKind::Int), null, ParameterMode::In)]),
            new Integral(IntegralKind::Int),
            new Block([]),
        );
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('A routine name has at most a database qualifier.');

        new CreateFunction(new QualifiedName(new Name('f'), new Name('shop'), new Name('def')), new ParameterList([]), new Integral(IntegralKind::Int), new Block([]));
    }
}
