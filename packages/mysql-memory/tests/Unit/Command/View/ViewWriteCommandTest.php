<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Command\View\ViewWriteCommand;
use MySqlMemory\Command\View\ViewWrites;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewWriteCommand::class)]
#[Small]
final class ViewWriteCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersThatOfTheCommand(): void
    {
        self::assertTrue((new ViewWriteCommand(new InsertCommand()))->clearsDiagnostics());
    }

    public function testExecuteWritesTheBaseTableOfAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, c INT DEFAULT 9)');
        $session->query('INSERT INTO t VALUES (1, 1, 1), (-1, 3, 3)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t q WHERE q.a > 0');

        $session->query('INSERT INTO w VALUES (8, 9)');
        $session->query('UPDATE w SET x = x * 100 WHERE b < 3');
        $session->query('DELETE FROM w WHERE x = 8');
        $result = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['100', '1', '1'], ['-1', '3', '3']], $result->rows);
    }

    public function testExecuteRefusesAViewThatIsNotUpdatable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW n AS SELECT DISTINCT a FROM t');

        $this->expectExceptionCode(1288);
        $this->expectExceptionMessage('The target table n of the DELETE is not updatable');

        $session->query('DELETE FROM n');
    }

    public function testExecuteNotesALimitThroughAViewWithoutAKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');

        $session->query('UPDATE v SET a = 0 LIMIT 1');

        self::assertSame([['Note', 1355, 'View being updated does not have complete key of underlying table in it']], $session->diagnostics->conditions);
    }

    public function testInsertRefusesAComputedColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW e AS SELECT a, b + 1 AS bb FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['e'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $session->text = 'INSERT INTO e VALUES (5, 6)';

        $this->expectExceptionCode(1348);
        $this->expectExceptionMessage("Column 'bb' is not updatable");

        (new ViewWriteCommand(new InsertCommand()))->insert(ProgramSource::of($session), $writes, 'e');
    }

    public function testInsertWritesTheBaseColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $session->text = 'INSERT INTO w (x) VALUES (1)';

        self::assertSame('INSERT INTO `d`.`t` (`a`) VALUES (1)', (new ViewWriteCommand(new InsertCommand()))->insert(ProgramSource::of($session), $writes, 'w'));
    }

    public function testChangeAddsTheConditionOfTheView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t q WHERE q.a > 0');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $session->text = 'UPDATE w SET x = x * 100 WHERE b < 3';

        self::assertSame('UPDATE `d`.`t` AS `q` SET `a` = `q`.`a` * 100 WHERE (`q`.`b` < 3) AND (q.a > 0)', (new ViewWriteCommand(new InsertCommand()))->change(ProgramSource::of($session), $writes, ['w'], $session));
    }

    public function testViewTargetAnswersTheViewAStatementWrites(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');

        $target = ViewWriteCommand::viewTarget($session->analyze('DELETE FROM v WHERE a = 1'));

        self::assertSame(['v', null, null], [$target?->name->name->value, ViewWriteCommand::viewTarget($session->analyze('DELETE FROM t')), ViewWriteCommand::viewTarget($session->analyze('SELECT 1'))]);
    }

    public function testNotedCountsTheNoteOfALimitThroughAViewWithoutAKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $writes = new ViewWrites('`d`.`t`', '`t`', '', [['a', '`a`', '`t`.`a`']], null);

        $reply = ViewWriteCommand::noted($session->analyze('DELETE FROM v LIMIT 1')->statement, $writes, new Completion(2, 0, 0), $session);

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 1, [['Note', 1355, 'View being updated does not have complete key of underlying table in it']]], [$reply->affectedRows, $reply->warnings, $session->diagnostics->conditions]);
    }

    public function testNotedAnswersTheReplyOfAStatementWithoutALimit(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $writes = new ViewWrites('`d`.`t`', '`t`', '', [['a', '`a`', '`t`.`a`']], null);
        $reply = new Completion(2, 0, 0);

        self::assertSame($reply, ViewWriteCommand::noted($session->analyze('DELETE FROM v')->statement, $writes, $reply, $session));
    }

    public function testRetargetWritesTheStatementAgainstTheBaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t q WHERE q.a > 0');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $session->text = 'UPDATE w SET x = b ORDER BY x LIMIT 2';

        self::assertSame('UPDATE `d`.`t` AS `q` SET `a` = `q`.`b` ORDER BY `q`.`a` LIMIT 2', (new ViewWriteCommand(new InsertCommand()))->retarget(ProgramSource::of($session), $writes, ['w']));
    }

    public function testRestrictedAddsTheConditionToTheConditionOfTheStatementOrAsItsWhereClause(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['UPDATE `d`.`t` SET a = 1 WHERE (a > 0)', 'DELETE FROM `d`.`t` WHERE (b = 1) AND (a > 0)'], [ViewWriteCommand::restricted('UPDATE `d`.`t` SET a = 1', 'a > 0', $session), ViewWriteCommand::restricted('DELETE FROM `d`.`t` WHERE b = 1', 'a > 0', $session)]);
    }

    public function testRestrictedPlacesTheConditionBeforeTheOrderAndTheLimit(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['DELETE FROM `d`.`t` WHERE (a > 0) LIMIT 1', 'DELETE FROM `d`.`t` AS `x` WHERE (a > 0) ORDER BY a LIMIT 1', 'UPDATE `d`.`t` SET a = 1 WHERE (a > 0) LIMIT 1'], [ViewWriteCommand::restricted('DELETE FROM `d`.`t` LIMIT 1', 'a > 0', $session), ViewWriteCommand::restricted('DELETE FROM `d`.`t` AS `x` ORDER BY a LIMIT 1', 'a > 0', $session), ViewWriteCommand::restricted('UPDATE `d`.`t` SET a = 1 LIMIT 1', 'a > 0', $session)]);
    }

    public function testLimitableReadsUpdatableViewsWithLimit(): void
    {
        $session = (new Instance())->connect();
        $before = ViewWriteCommand::limitable($session);
        $session->query('SET SESSION updatable_views_with_limit = NO');

        self::assertSame([true, false], [$before, ViewWriteCommand::limitable($session)]);
    }

    public function testExecuteRefusesALimitThroughAViewWithoutAKeyWhenTheSessionForbidsIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $session->query('SET SESSION updatable_views_with_limit = NO');

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage('The target table v of the DELETE is not updatable');

        $session->query('DELETE FROM v LIMIT 1');
    }
}
