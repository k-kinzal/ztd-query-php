<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\BodyFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(BodyFacts::class)]
#[Medium]
final class BodyFactsTest extends TestCase
{
    public function testStatementsDerivesEveryStatementInOrder(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN SELECT y; SELECT 1; SELECT z; END');

        self::assertSame(['Column y does not exist.', 'Column z does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    public function testStatementResolvesAVariableInANestedQuery(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; SELECT a FROM t WHERE a = x; END', [$table]);
        $create = $operation;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $block->statements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $where = $select->where;
        self::assertInstanceOf(Comparison::class, $where);
        $resolution = $where->right->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->diagnostics());
        self::assertSame($block->declarations[0], $resolution->relation);
        self::assertSame(['Column y does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; SELECT a FROM t WHERE a = y; END', [$table])->diagnostics()));
    }

    public function testStatementResolvesAVariableInAnInspectedStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO BEGIN DECLARE x INT; UPDATE t SET a = x; END', [$table]);
        $create = $operation;
        self::assertInstanceOf(CreateEvent::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $update = $block->statements[0];
        self::assertInstanceOf(Update::class, $update);
        $resolution = $update->assignments[0]->value->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->diagnostics());
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerStatementKeepsTheDiagnosticsOfAnSqlStatement')]
    public function testStatementKeepsTheDiagnosticsOfAnSqlStatement(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatementKeepsTheDiagnosticsOfAnSqlStatement(): iterable
    {
        yield 'an INSERT of a procedure' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; INSERT INTO t (a) VALUES (x); END', []];
        yield 'an INSERT into an unknown column' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; INSERT INTO t (zz) VALUES (x); END', ['Column zz does not exist.']];
        yield 'an UPDATE with an unknown name' => ['CREATE PROCEDURE p(x INT) UPDATE t SET a = x WHERE a = y', ['Column y does not exist.']];
        yield 'the DELETE of an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DELETE FROM t WHERE zz = 1', ['Column zz does not exist.']];
        yield 'the query of an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT a FROM t', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerStatementChecksTheRestrictionsOfTheProgramKind')]
    public function testStatementChecksTheRestrictionsOfTheProgramKind(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatementChecksTheRestrictionsOfTheProgramKind(): iterable
    {
        yield 'a result set of a procedure' => ['CREATE PROCEDURE p() SELECT 1', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerStatementChecksTheAssignmentsOfATrigger')]
    public function testStatementChecksTheAssignmentsOfATrigger(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatementChecksTheAssignmentsOfATrigger(): iterable
    {
        yield 'NEW in a BEFORE INSERT trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.a = 1', []];
        yield 'NEW in an AFTER INSERT trigger' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1', ['Updating of NEW row is not allowed in after trigger']];
        yield 'NEW in a DELETE trigger' => ['CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW SET NEW.a = 1', ['There is no NEW row in on DELETE trigger']];
        yield 'OLD in a nested statement' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW BEGIN DECLARE x INT; SET NEW.a = x; IF NEW.a > 0 THEN SET OLD.a = 1; END IF; END', ['Updating of OLD row is not allowed in trigger']];
        yield 'NEW in a block of an AFTER trigger' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW BEGIN SET NEW.a = 1; END', ['Updating of NEW row is not allowed in after trigger']];
    }

    /**
     * @return iterable<string, array{string, non-empty-list<string>}>
     */
    public static function providerRetainsIntrinsicBodyConditions(): iterable
    {
        yield 'a result set of a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1; RETURN 1; END', ['Not allowed to return a result set from a function']];
        yield 'a commit in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN COMMIT; RETURN 1; END', ['Explicit or implicit commit is not allowed in stored function or trigger.']];
        yield 'a result set of a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SELECT 1', ['Not allowed to return a result set from a trigger']];
    }

    /**
     * @param non-empty-list<string> $messages
     */
    #[DataProvider('providerRetainsIntrinsicBodyConditions')]
    public function testRetainsIntrinsicBodyConditions(string $sql, array $messages): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame($messages[0], $notices[0]->message());

    }
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerNestedDeclarationRefusals')]
    public function testNestedDeclarationsRetainTheirInputRefusal(string $sql, array $messages): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage($messages[0]);
        (new Semantics(Dialect::MySql))->analyze($sql);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerNestedDeclarationRefusals(): iterable
    {
        yield 'a procedure created in a procedure' => ['CREATE PROCEDURE p() BEGIN CREATE PROCEDURE q() BEGIN END; END', ["Can't create a PROCEDURE from within another stored routine"]];
    }

}
