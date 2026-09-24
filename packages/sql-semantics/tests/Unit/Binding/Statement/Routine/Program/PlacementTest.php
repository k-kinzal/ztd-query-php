<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Placement;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Placement::class)]
#[Medium]
final class PlacementTest extends TestCase
{
    #[TestWith(['CREATE PROCEDURE q() BEGIN END'])]
    #[TestWith(['CREATE FUNCTION g() RETURNS INT RETURN 1'])]
    #[TestWith(['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO 1'])]
    #[TestWith(['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DO 1'])]
    #[TestWith(['ALTER EVENT e DO DO 1'])]
    public function testNestedDiagnosesStoredProgramDefinitionsInABody(string $nested): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramStatement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE PROCEDURE p() BEGIN ' . $nested . '; END', strict: false);
    }

    public function testNestedAcceptsOrdinaryStatements(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER EVENT e ENABLE');
        Placement::nested(Tree::outer($tree, ['alter_event_stmt'])[0]);
        self::assertSame('ALTER EVENT e ENABLE', $tree->toString());
    }

    #[TestWith(['mysql-8.4.7', 'DROP FUNCTION f'])]
    #[TestWith(['mysql-8.4.7', 'ALTER VIEW v AS SELECT 1'])]
    #[TestWith(['mysql-8.4.7', 'LOCK TABLES t READ'])]
    #[TestWith(['mysql-8.4.7', 'UNLOCK TABLES'])]
    #[TestWith(['mysql-8.4.7', "LOAD DATA INFILE 'f' INTO TABLE t"])]
    #[TestWith(['mysql-8.4.7', 'USE db'])]
    #[TestWith(['mysql-5.7.44', 'CHECK TABLE t'])]
    #[TestWith(['mysql-5.6.51', 'DROP DATABASE db'])]
    #[TestWith(['mysql-5.7.44', 'HANDLER t OPEN'])]
    public function testCheckDiagnosesStatementsNoProgramMayRun(string $version, string $statement): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramStatement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (n INT)')))->bind('CREATE PROCEDURE p() ' . $statement, strict: false);
    }

    public function testCheckAcceptsTheSameStatementsInProceduresOnLaterReleases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build('CREATE TABLE t (n INT)')))->bind('CREATE PROCEDURE p() BEGIN CHECK TABLE t; SELECT n FROM t; COMMIT; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
    }

    #[TestWith(['SELECT 1'])]
    #[TestWith(['SHOW TABLES'])]
    #[TestWith(['COMMIT'])]
    #[TestWith(['CREATE TABLE x (a INT)'])]
    #[TestWith(['FLUSH TABLES'])]
    #[TestWith(["PREPARE s FROM 'SELECT 1'"])]
    #[TestWith(['START TRANSACTION'])]
    public function testFunctionForbiddenCoversResultsCommitsAndDynamicSql(string $statement): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramStatement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE FUNCTION f() RETURNS INT BEGIN ' . $statement . '; RETURN 1; END', strict: false);
    }

    public function testFunctionForbiddenAllowsTemporaryTablesAndSavepoints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT BEGIN CREATE TEMPORARY TABLE x (a INT); SAVEPOINT s; ROLLBACK TO SAVEPOINT s; DROP TEMPORARY TABLE x; RETURN 1; END');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
    }
}
