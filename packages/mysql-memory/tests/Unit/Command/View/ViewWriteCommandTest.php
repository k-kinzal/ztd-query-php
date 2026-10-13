<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewWriteCommand;
use MySqlMemory\Command\View\ViewWrites;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

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
        $writes = new ViewWrites(new QualifiedName(new Name('t'), new Name('d')), new Name('t'), null, [['a', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))]], null);

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
        $writes = new ViewWrites(new QualifiedName(new Name('t'), new Name('d')), new Name('t'), null, [['a', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))]], null);
        $reply = new Completion(2, 0, 0);

        self::assertSame($reply, ViewWriteCommand::noted($session->analyze('DELETE FROM v')->statement, $writes, $reply, $session));
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
