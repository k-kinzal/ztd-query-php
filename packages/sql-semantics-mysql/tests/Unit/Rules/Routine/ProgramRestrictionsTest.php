<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramRestrictions;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Query;

#[CoversClass(ProgramRestrictions::class)]
#[Medium]
final class ProgramRestrictionsTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCheckReportsAStatementNoStoredProgramMayContain')]
    public function testCheckReportsAStatementNoStoredProgramMayContain(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerCheckReportsAStatementNoStoredProgramMayContain(): iterable
    {
        yield 'LOCK TABLES in a procedure' => ['CREATE PROCEDURE p() LOCK TABLES t READ', ['LOCK is not allowed in stored procedures']];
        yield 'LOCK TABLES in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN LOCK TABLES t READ; RETURN 1; END', ['LOCK is not allowed in stored procedures']];
        yield 'LOCK TABLES in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW LOCK TABLES t READ', ['LOCK is not allowed in stored procedures']];
        yield 'LOCK TABLES in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO LOCK TABLES t READ', ['LOCK is not allowed in stored procedures']];
        yield 'UNLOCK TABLES in a procedure' => ['CREATE PROCEDURE p() UNLOCK TABLES', ['UNLOCK is not allowed in stored procedures']];
        yield 'UNLOCK TABLES in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO UNLOCK TABLES', ['UNLOCK is not allowed in stored procedures']];
        yield 'ALTER VIEW in a trigger' => ['CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW ALTER VIEW v AS SELECT 1', ['ALTER VIEW is not allowed in stored procedures', 'Relation v does not exist.']];
        yield 'LOAD DATA in a procedure' => ["CREATE PROCEDURE p() LOAD DATA INFILE 'f' INTO TABLE t", ['LOAD DATA is not allowed in stored procedures']];
        yield 'LOAD DATA in a trigger' => ["CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW LOAD DATA INFILE 'f' INTO TABLE t", ['LOAD DATA is not allowed in stored procedures']];
        yield 'LOAD XML in a function' => ["CREATE FUNCTION f() RETURNS INT BEGIN LOAD XML INFILE 'f' INTO TABLE t; RETURN 1; END", ['LOAD XML is not allowed in stored procedures']];
        yield 'USE in a procedure' => ['CREATE PROCEDURE p() USE db', ['USE is not allowed in stored procedures']];
        yield 'USE in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO USE db', ['USE is not allowed in stored procedures']];
        yield 'CREATE PROCEDURE in a procedure' => ['CREATE PROCEDURE p() CREATE PROCEDURE q() SELECT 1', ["Can't create a PROCEDURE from within another stored routine"]];
        yield 'CREATE FUNCTION in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN CREATE FUNCTION g() RETURNS INT RETURN 1; RETURN 1; END', ["Can't create a FUNCTION from within another stored routine"]];
        yield 'CREATE TRIGGER in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW CREATE TRIGGER tr2 BEFORE INSERT ON t FOR EACH ROW SET @a = 1', ["Can't create a TRIGGER from within another stored routine"]];
        yield 'CREATE TRIGGER in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a = 1', ["Can't create a TRIGGER from within another stored routine"]];
        yield 'CREATE EVENT in a procedure' => ['CREATE PROCEDURE p() CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1', ['Recursion of EVENT DDL statements is forbidden when body is present']];
        yield 'CREATE EVENT in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO CREATE EVENT e2 ON SCHEDULE EVERY 1 DAY DO SELECT 1', ['Recursion of EVENT DDL statements is forbidden when body is present']];
        yield 'ALTER EVENT with a body in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW ALTER EVENT e DO SET @a = 1', ['Recursion of EVENT DDL statements is forbidden when body is present']];
        yield 'ALTER PROCEDURE in a function' => ["CREATE FUNCTION f() RETURNS INT BEGIN ALTER PROCEDURE q COMMENT 'x'; RETURN 1; END", ["Can't drop or alter a PROCEDURE from within another stored routine"]];
        yield 'ALTER FUNCTION in a procedure' => ["CREATE PROCEDURE p() ALTER FUNCTION g COMMENT 'x'", ["Can't drop or alter a FUNCTION from within another stored routine"]];
        yield 'DROP PROCEDURE in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DROP PROCEDURE q', ["Can't drop or alter a PROCEDURE from within another stored routine"]];
        yield 'DROP FUNCTION in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DROP FUNCTION IF EXISTS g', ["Can't drop or alter a FUNCTION from within another stored routine"]];
    }

    #[DataProvider('providerCheckAcceptsAStatementEveryStoredProgramMayContain')]
    public function testCheckAcceptsAStatementEveryStoredProgramMayContain(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')])->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCheckAcceptsAStatementEveryStoredProgramMayContain(): iterable
    {
        yield 'ALTER EVENT without body in a procedure' => ["CREATE PROCEDURE p() ALTER EVENT e COMMENT 'x'"];
        yield 'ALTER EVENT without body in a function' => ["CREATE FUNCTION f() RETURNS INT BEGIN ALTER EVENT e COMMENT 'x'; RETURN 1; END"];
        yield 'DROP TRIGGER in a trigger' => ['CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW DROP TRIGGER tr2'];
        yield 'DROP EVENT in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DROP EVENT e2'];
        yield 'a user variable assignment in a procedure' => ['CREATE PROCEDURE p() SET @a = 1'];
        yield 'a user variable assignment in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SET @a = 1; RETURN @a; END'];
        yield 'an INSERT in a trigger' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW INSERT INTO t (a) VALUES (NEW.a)'];
        yield 'a SELECT INTO in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT a INTO @a FROM t'];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCheckReportsAStatementAStoredFunctionOrTriggerMayNotContain')]
    public function testCheckReportsAStatementAStoredFunctionOrTriggerMayNotContain(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerCheckReportsAStatementAStoredFunctionOrTriggerMayNotContain(): iterable
    {
        yield 'a SELECT in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'a SELECT in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SELECT 1', ['Not allowed to return a result set from a trigger']];
        yield 'a UNION in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1 UNION SELECT 2; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'a TABLE statement in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW TABLE t', ['Not allowed to return a result set from a trigger']];
        yield 'a VALUES statement in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN VALUES ROW(1); RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'SHOW in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SHOW VARIABLES; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'SHOW in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SHOW TABLES', ['Not allowed to return a result set from a trigger']];
        yield 'EXPLAIN in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW EXPLAIN SELECT 1', ['Not allowed to return a result set from a trigger']];
        yield 'EXPLAIN FOR CONNECTION in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN EXPLAIN FOR CONNECTION 1; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'DESCRIBE in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN DESCRIBE t; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'HELP in a trigger' => ["CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW HELP 'x'", ['Not allowed to return a result set from a trigger']];
        yield 'CHECK TABLE in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN CHECK TABLE t; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'ANALYZE TABLE in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW ANALYZE TABLE t', ['Not allowed to return a result set from a trigger']];
        yield 'OPTIMIZE TABLE in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN OPTIMIZE TABLE t; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'REPAIR TABLE in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW REPAIR TABLE t', ['Not allowed to return a result set from a trigger']];
        yield 'CHECKSUM TABLE in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN CHECKSUM TABLE t; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'COMMIT in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN COMMIT; RETURN 1; END', ['Explicit or implicit commit is not allowed in stored function or trigger.']];
        yield 'ROLLBACK in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW ROLLBACK', ['Explicit or implicit commit is not allowed in stored function or trigger.']];
        yield 'START TRANSACTION in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW START TRANSACTION', ['Explicit or implicit commit is not allowed in stored function or trigger.']];
        yield 'PREPARE in a function' => ["CREATE FUNCTION f() RETURNS INT BEGIN PREPARE s FROM 'SELECT 1'; RETURN 1; END", ['Dynamic SQL is not allowed in stored function or trigger']];
        yield 'EXECUTE in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW EXECUTE s', ['Dynamic SQL is not allowed in stored function or trigger']];
        yield 'DEALLOCATE PREPARE in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN DEALLOCATE PREPARE s; RETURN 1; END', ['Dynamic SQL is not allowed in stored function or trigger']];
        yield 'FLUSH in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN FLUSH PRIVILEGES; RETURN 1; END', ['FLUSH is not allowed in stored function or trigger']];
        yield 'FLUSH TABLES in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW FLUSH TABLES', ['FLUSH is not allowed in stored function or trigger']];
        yield 'RESET REPLICA in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN RESET REPLICA; RETURN 1; END', ['RESET is not allowed in stored function or trigger']];
        yield 'RESET BINARY LOGS AND GTIDS in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RESET BINARY LOGS AND GTIDS', ['RESET is not allowed in stored function or trigger']];
        yield 'RESET PERSIST in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN RESET PERSIST; RETURN 1; END', ['RESET is not allowed in stored function or trigger']];
        yield 'RESET PERSIST of a variable in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RESET PERSIST IF EXISTS max_connections', ['RESET is not allowed in stored function or trigger']];
    }

    #[DataProvider('providerCheckAcceptsTheseStatementsInAProcedureOrAnEvent')]
    public function testCheckAcceptsTheseStatementsInAProcedureOrAnEvent(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')])->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCheckAcceptsTheseStatementsInAProcedureOrAnEvent(): iterable
    {
        yield 'a SELECT in a procedure' => ['CREATE PROCEDURE p() SELECT 1'];
        yield 'a SELECT in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1'];
        yield 'SHOW in a procedure' => ['CREATE PROCEDURE p() SHOW TABLES'];
        yield 'EXPLAIN in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO EXPLAIN SELECT 1'];
        yield 'DESCRIBE in a procedure' => ['CREATE PROCEDURE p() DESCRIBE t'];
        yield 'CHECK TABLE in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO CHECK TABLE t'];
        yield 'COMMIT in a procedure' => ['CREATE PROCEDURE p() COMMIT'];
        yield 'START TRANSACTION in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO START TRANSACTION'];
        yield 'PREPARE in a procedure' => ["CREATE PROCEDURE p() PREPARE s FROM 'SELECT 1'"];
        yield 'EXECUTE in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO EXECUTE s'];
        yield 'FLUSH in a procedure' => ['CREATE PROCEDURE p() FLUSH PRIVILEGES'];
        yield 'RESET in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO RESET REPLICA'];
        yield 'RESET PERSIST in a procedure' => ['CREATE PROCEDURE p() RESET PERSIST'];
        yield 'a SELECT INTO in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1 INTO @a; RETURN 1; END'];
        yield 'a UNION INTO in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SELECT 1 UNION SELECT 2 INTO @a'];
        yield 'a SELECT INTO OUTFILE in a function' => ["CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1 INTO OUTFILE 'x'; RETURN 1; END"];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCheckReportsTheStatementWhereverItIsNested')]
    public function testCheckReportsTheStatementWhereverItIsNested(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerCheckReportsTheStatementWhereverItIsNested(): iterable
    {
        yield 'in an IF of a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN IF 1 THEN COMMIT; END IF; RETURN 1; END', ['Explicit or implicit commit is not allowed in stored function or trigger.']];
        yield 'in a handler of a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SELECT 1; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'in a handler of a procedure' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION LOCK TABLES t READ; END', ['LOCK is not allowed in stored procedures']];
        yield 'in a loop of a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN WHILE 1 DO FLUSH TABLES; END WHILE; END', ['FLUSH is not allowed in stored function or trigger']];
        yield 'in a block of an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO BEGIN BEGIN USE db; END; END', ['USE is not allowed in stored procedures']];
        yield 'twice in one body' => ['CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1; COMMIT; RETURN 1; END', ['Not allowed to return a result set from a function', 'Explicit or implicit commit is not allowed in stored function or trigger.']];
        yield 'once when two rules apply' => ['CREATE FUNCTION f() RETURNS INT BEGIN CREATE PROCEDURE q() SELECT 1; RETURN 1; END', ["Can't create a PROCEDURE from within another stored routine"]];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCheckReportsTheStatementInEveryRelease')]
    public function testCheckReportsTheStatementInEveryRelease(string $release, string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerCheckReportsTheStatementInEveryRelease(): iterable
    {
        yield 'RESET MASTER in a 5.6 function' => ['mysql-5.6.51', 'CREATE FUNCTION f() RETURNS INT BEGIN RESET MASTER; RETURN 1; END', ['RESET is not allowed in stored function or trigger']];
        yield 'RESET QUERY CACHE in a 5.7 trigger' => ['mysql-5.7.44', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RESET QUERY CACHE', ['RESET is not allowed in stored function or trigger']];
        yield 'RESET SLAVE in a 5.7 procedure' => ['mysql-5.7.44', 'CREATE PROCEDURE p() RESET SLAVE', []];
        yield 'a SELECT in an 8.0 trigger' => ['mysql-8.0.44', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SELECT a FROM t', ['Not allowed to return a result set from a trigger']];
        yield 'LOCK TABLES in a 5.6 procedure' => ['mysql-5.6.51', 'CREATE PROCEDURE p() LOCK TABLES t WRITE', ['LOCK is not allowed in stored procedures']];
        yield 'a 5.7 union with a locked middle operand in a function' => ['mysql-5.7.44', 'CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1 UNION SELECT 2 FROM t FOR UPDATE UNION SELECT 3; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'a 5.7 union with a locked middle operand INTO in a function' => ['mysql-5.7.44', 'CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1 UNION SELECT 2 FROM t FOR UPDATE UNION SELECT 3 INTO @a; RETURN 1; END', []];
        yield 'LOAD XML in an 8.4 event' => ['mysql-8.4.7', "CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO LOAD XML INFILE 'f' INTO TABLE t", ['LOAD XML is not allowed in stored procedures']];
    }

    #[DataProvider('providerAnywhereAnswersTheProblemOfTheStatement')]
    public function testAnywhereAnswersTheProblemOfTheStatement(string $sql, ?string $message): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql)->statement;

        self::assertSame($message, (new ProgramRestrictions())->anywhere($statement)?->message());
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerAnywhereAnswersTheProblemOfTheStatement(): iterable
    {
        yield 'LOCK TABLES' => ['LOCK TABLES t READ', 'LOCK is not allowed in stored procedures'];
        yield 'UNLOCK TABLES' => ['UNLOCK TABLES', 'UNLOCK is not allowed in stored procedures'];
        yield 'ALTER VIEW' => ['ALTER VIEW v AS SELECT 1', 'ALTER VIEW is not allowed in stored procedures'];
        yield 'LOAD DATA' => ["LOAD DATA INFILE 'f' INTO TABLE t", 'LOAD DATA is not allowed in stored procedures'];
        yield 'LOAD XML' => ["LOAD XML INFILE 'f' INTO TABLE t", 'LOAD XML is not allowed in stored procedures'];
        yield 'USE' => ['USE db', 'USE is not allowed in stored procedures'];
        yield 'CREATE PROCEDURE' => ['CREATE PROCEDURE q() SELECT 1', "Can't create a PROCEDURE from within another stored routine"];
        yield 'CREATE FUNCTION' => ['CREATE FUNCTION g() RETURNS INT RETURN 1', "Can't create a FUNCTION from within another stored routine"];
        yield 'CREATE TRIGGER' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a = 1', "Can't create a TRIGGER from within another stored routine"];
        yield 'CREATE EVENT' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1', 'Recursion of EVENT DDL statements is forbidden when body is present'];
        yield 'ALTER EVENT with a body' => ['ALTER EVENT e DO SELECT 1', 'Recursion of EVENT DDL statements is forbidden when body is present'];
        yield 'ALTER EVENT without body' => ['ALTER EVENT e DISABLE', null];
        yield 'ALTER PROCEDURE' => ["ALTER PROCEDURE q COMMENT 'x'", "Can't drop or alter a PROCEDURE from within another stored routine"];
        yield 'ALTER FUNCTION' => ["ALTER FUNCTION g COMMENT 'x'", "Can't drop or alter a FUNCTION from within another stored routine"];
        yield 'DROP PROCEDURE' => ['DROP PROCEDURE IF EXISTS q', "Can't drop or alter a PROCEDURE from within another stored routine"];
        yield 'DROP FUNCTION' => ['DROP FUNCTION g', "Can't drop or alter a FUNCTION from within another stored routine"];
        yield 'DROP TRIGGER' => ['DROP TRIGGER tr', null];
        yield 'DROP EVENT' => ['DROP EVENT e', null];
        yield 'a SELECT' => ['SELECT 1', null];
        yield 'COMMIT' => ['COMMIT', null];
    }

    #[DataProvider('providerFunctionAnswersTheProblemOfTheStatement')]
    public function testFunctionAnswersTheProblemOfTheStatement(string $sql, ProgramKind $kind, ?string $message): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql)->statement;

        self::assertSame($message, (new ProgramRestrictions())->function($statement, $kind)?->message());
    }

    /**
     * @return iterable<string, array{string, ProgramKind, string|null}>
     */
    public static function providerFunctionAnswersTheProblemOfTheStatement(): iterable
    {
        yield 'a SELECT of a function' => ['SELECT 1', ProgramKind::Function, 'Not allowed to return a result set from a function'];
        yield 'a SELECT of a trigger' => ['SELECT 1', ProgramKind::Trigger, 'Not allowed to return a result set from a trigger'];
        yield 'a SELECT INTO' => ['SELECT 1 INTO @a', ProgramKind::Function, null];
        yield 'SHOW' => ['SHOW TABLES', ProgramKind::Trigger, 'Not allowed to return a result set from a trigger'];
        yield 'COMMIT' => ['COMMIT', ProgramKind::Function, 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'ROLLBACK' => ['ROLLBACK', ProgramKind::Trigger, 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'START TRANSACTION' => ['START TRANSACTION', ProgramKind::Function, 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'PREPARE' => ["PREPARE s FROM 'SELECT 1'", ProgramKind::Function, 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'EXECUTE' => ['EXECUTE s USING @a', ProgramKind::Trigger, 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'DEALLOCATE PREPARE' => ['DROP PREPARE s', ProgramKind::Function, 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'FLUSH' => ['FLUSH LOGS', ProgramKind::Function, 'FLUSH is not allowed in stored function or trigger'];
        yield 'FLUSH TABLES' => ['FLUSH TABLES t', ProgramKind::Trigger, 'FLUSH is not allowed in stored function or trigger'];
        yield 'RESET' => ['RESET REPLICA', ProgramKind::Function, 'RESET is not allowed in stored function or trigger'];
        yield 'RESET PERSIST' => ['RESET PERSIST max_connections', ProgramKind::Trigger, 'RESET is not allowed in stored function or trigger'];
        yield 'LOCK TABLES' => ['LOCK TABLES t READ', ProgramKind::Function, null];
        yield 'a user variable assignment' => ['SET @a = 1', ProgramKind::Trigger, null];
    }

    #[DataProvider('providerResultsTellsWhetherTheStatementReturnsRows')]
    public function testResultsTellsWhetherTheStatementReturnsRows(string $sql, bool $expected): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql)->statement;

        self::assertSame($expected, (new ProgramRestrictions())->results($statement));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerResultsTellsWhetherTheStatementReturnsRows(): iterable
    {
        yield 'a SELECT' => ['SELECT 1', true];
        yield 'a SELECT INTO' => ['SELECT 1 INTO @a', false];
        yield 'a TABLE statement' => ['TABLE t', true];
        yield 'EXPLAIN' => ['EXPLAIN SELECT 1', true];
        yield 'EXPLAIN FOR CONNECTION' => ['EXPLAIN FOR CONNECTION 1', true];
        yield 'DESCRIBE' => ['DESCRIBE t', true];
        yield 'HELP' => ["HELP 'x'", true];
        yield 'CHECK TABLE' => ['CHECK TABLE t', true];
        yield 'ANALYZE TABLE' => ['ANALYZE TABLE t', true];
        yield 'OPTIMIZE TABLE' => ['OPTIMIZE TABLE t', true];
        yield 'REPAIR TABLE' => ['REPAIR TABLE t', true];
        yield 'CHECKSUM TABLE' => ['CHECKSUM TABLE t', true];
        yield 'SHOW TABLES' => ['SHOW TABLES', true];
        yield 'SHOW VARIABLES' => ['SHOW VARIABLES', true];
        yield 'an INSERT' => ['INSERT INTO t VALUES (1, 2)', false];
        yield 'a SET' => ['SET @a = 1', false];
        yield 'COMMIT' => ['COMMIT', false];
    }

    #[DataProvider('providerIntoTellsWhetherTheQueryWritesItsRows')]
    public function testIntoTellsWhetherTheQueryWritesItsRows(string $sql, bool $expected): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze($sql)->statement;
        self::assertInstanceOf(Query::class, $query);

        self::assertSame($expected, (new ProgramRestrictions())->into($query));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerIntoTellsWhetherTheQueryWritesItsRows(): iterable
    {
        yield 'a SELECT' => ['SELECT 1', false];
        yield 'a SELECT INTO a variable' => ['SELECT 1 INTO @a', true];
        yield 'a SELECT INTO a file' => ["SELECT 1 INTO OUTFILE 'x'", true];
        yield 'a SELECT INTO before a locking clause' => ['SELECT 1 INTO @a FOR UPDATE', true];
        yield 'a SELECT INTO after a locking clause' => ['SELECT 1 FROM t FOR UPDATE INTO @a', true];
        yield 'a UNION' => ['SELECT 1 UNION SELECT 2', false];
        yield 'a UNION INTO' => ['SELECT 1 UNION SELECT 2 INTO @a', true];
        yield 'a UNION with a parenthesized operand INTO' => ['(SELECT 1) UNION (SELECT 2 INTO @a)', true];
        yield 'a parenthesized SELECT INTO' => ['(SELECT 1 INTO @a)', true];
        yield 'an ordered query INTO' => ['(SELECT 1) ORDER BY 1 INTO @a', true];
        yield 'an ordered UNION INTO' => ['SELECT 1 UNION SELECT 2 ORDER BY 1 INTO @a', true];
        yield 'a query with WITH INTO' => ['WITH c AS (SELECT 1) SELECT * FROM c INTO @a', true];
        yield 'a query with WITH' => ['WITH c AS (SELECT 1) SELECT * FROM c', false];
        yield 'a TABLE statement' => ['TABLE t', false];
        yield 'a VALUES statement' => ['VALUES ROW(1)', false];
    }
}
