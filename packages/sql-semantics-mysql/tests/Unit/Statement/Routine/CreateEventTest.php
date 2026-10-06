<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreateEvent::class)]
#[Medium]
final class CreateEventTest extends TestCase
{
    public function testDeriveStatementResolvesTheLocalVariablesInEveryBodyStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY STARTS NOW() DO BEGIN DECLARE v INT DEFAULT 1; '
            . 'UPDATE t SET b = v WHERE a = v; INSERT INTO t (a) VALUES (v); DELETE FROM t WHERE b = v; SET @w = v; SELECT a, v FROM t; END', [$table]);
        $statement = $create->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveStatementReportsAnUnknownNameOfTheBody(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE AT NOW() DO DELETE FROM t WHERE zz = 1', [$table]);

        self::assertSame(['Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementReportsAColumnInTheSchedule(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY a DAY DO SELECT 1', [$table]);

        self::assertSame(['Column a does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementReportsANestedEventDefinition(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE EVENT e ON SCHEDULE AT NOW() DO CREATE EVENT f ON SCHEDULE AT NOW() DO SELECT 1');
        $problem = $create->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $create->facts->diagnostics);
        self::assertSame(ProgramRule::EventRecursion, $problem->rule);
        self::assertSame('Recursion of EVENT DDL statements is forbidden when body is present', $problem->message());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDeriveStatementReportsAStatementAStoredProgramMayNotContain(): iterable
    {
        yield 'LOCK TABLES' => ['LOCK TABLES t READ', 'LOCK is not allowed in stored procedures'];
        yield 'RETURN' => ['RETURN 1', 'RETURN is only allowed in a FUNCTION'];
        yield 'CREATE PROCEDURE' => ['CREATE PROCEDURE p() SELECT 1', "Can't create a PROCEDURE from within another stored routine"];
        yield 'ALTER EVENT with a body' => ['ALTER EVENT f DO SELECT 1', 'Recursion of EVENT DDL statements is forbidden when body is present'];
    }

    #[DataProvider('providerDeriveStatementReportsAStatementAStoredProgramMayNotContain')]
    public function testDeriveStatementReportsAStatementAStoredProgramMayNotContain(string $body, string $message): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE EVENT e ON SCHEDULE AT NOW() DO ' . $body);

        self::assertSame([$message], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeriveStatementAcceptsWhatAnEventMayContain(): iterable
    {
        yield 'result set and commit' => ['BEGIN SELECT 1; COMMIT; END'];
        yield 'DROP EVENT' => ['DROP EVENT e'];
        yield 'ALTER EVENT without a body' => ['ALTER EVENT f DISABLE'];
    }

    #[DataProvider('providerDeriveStatementAcceptsWhatAnEventMayContain')]
    public function testDeriveStatementAcceptsWhatAnEventMayContain(string $body): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE EVENT e ON SCHEDULE AT NOW() DO ' . $body)->facts->diagnostics);
    }

    public function testDeriveStatementKeepsTheClauses(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("CREATE EVENT e ON SCHEDULE EVERY 1 DAY ON COMPLETION PRESERVE DISABLE COMMENT 'nightly' DO SELECT 1")->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);

        self::assertSame(Completion::Preserve, $statement->completion);
        self::assertSame(EventStatus::Disable, $statement->status);
        self::assertSame('nightly', $statement->comment?->value);
        self::assertFalse($statement->ifNotExists);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDefinition(): iterable
    {
        yield 'MySQL 5.6 definer and DISABLE ON SLAVE' => [
            'mysql-5.6.51',
            "create definer = 'u'@'h' event if not exists shop.e on schedule every 1 hour disable on slave do select 1",
            'CREATE DEFINER = u@h EVENT IF NOT EXISTS shop.e ON SCHEDULE EVERY 1 HOUR DISABLE ON SLAVE DO SELECT 1',
        ];
        yield 'MySQL 5.7 every clause' => [
            'mysql-5.7.44',
            "create event e on schedule every '1:30' hour_minute starts '2030-01-01' ends '2031-01-01' on completion preserve disable on slave comment 'c' do begin declare v int default 1; set @x = v; end",
            "CREATE EVENT e ON SCHEDULE EVERY '1:30' HOUR_MINUTE STARTS '2030-01-01' ENDS '2031-01-01' ON COMPLETION PRESERVE DISABLE ON SLAVE COMMENT 'c' DO BEGIN DECLARE v INT DEFAULT 1; SET @x = v; END",
        ];
        yield 'MySQL 8.4 DISABLE ON REPLICA' => [
            'mysql-8.4.7',
            'create event e on schedule every 1 hour disable on replica do select 1',
            'CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DISABLE ON REPLICA DO SELECT 1',
        ];
        yield 'MySQL 9.1 one-time schedule' => [
            'mysql-9.1.0',
            "create event e on schedule at current_timestamp + interval 1 day on completion not preserve enable comment 'c' do delete from t where a = 1",
            "CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY ON COMPLETION NOT PRESERVE ENABLE COMMENT 'c' DO DELETE FROM t WHERE a = 1",
        ];
    }

    #[DataProvider('providerRenderWritesTheDefinition')]
    public function testRenderWritesTheDefinition(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('An event name has at most a database qualifier.');

        new CreateEvent(new QualifiedName(new Name('e'), new Name('shop'), new Name('def')), new OnceSchedule(new NumberLiteral('1')), new Block([]));
    }

    public function testAHexadecimalCommentIsRejected(): void
    {
        $this->expectExceptionMessage('A comment is written as a quoted string.');

        new CreateEvent(new QualifiedName(new Name('e')), new OnceSchedule(new NumberLiteral('1')), new Block([]), null, null, new Text('41', EscapeRule::Backslash, Radix::Hexadecimal));
    }
}
