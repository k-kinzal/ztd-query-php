<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Triggers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Triggers::class)]
#[Small]
final class TriggersTest extends TestCase
{
    public function testOfAnswersTheTriggersInTheirOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER t1 BEFORE INSERT ON t FOR EACH ROW SET @x = 1');
        $session->query('CREATE TRIGGER t2 BEFORE INSERT ON t FOR EACH ROW PRECEDES t1 SET @x = 2');
        $session->query('CREATE TRIGGER t3 AFTER INSERT ON t FOR EACH ROW SET @x = 3');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(['t2', 't1'], array_map(static fn ($trigger): string => $trigger->name, (new Triggers($session, $table))->of('BEFORE', 'INSERT')));
    }

    public function testHasTellsWhetherAnEventHasTriggers(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER t1 AFTER DELETE ON t FOR EACH ROW SET @x = 1');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([true, false], [(new Triggers($session, $table))->has('DELETE'), (new Triggers($session, $table))->has('INSERT')]);
    }

    public function testBeforeLetsATriggerChangeTheNewRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, a INT, b INT)');
        $session->query("CREATE TRIGGER bi BEFORE INSERT ON t FOR EACH ROW BEGIN SET @seen = CONCAT(NEW.id, ' ', NEW.a); SET NEW.b = NEW.a * 10; END");
        $session->query('INSERT INTO t (a) VALUES (1)');

        $result1 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        $result2 = $session->query('SELECT @seen')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([[['1', '1', '10']], [['0 1']]], [$result1->rows, $result2->rows]);
    }

    public function testAfterFiresForEveryMatchedRowOfAnUpdate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $session->query("CREATE TRIGGER au AFTER UPDATE ON t FOR EACH ROW SET @m = CONCAT(IFNULL(@m, ''), OLD.a, '>', NEW.a, ';')");
        $session->query('UPDATE t SET a = a');

        $result3 = $session->query('SELECT @m')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([['1>1;2>2;']], $result3->rows);
    }

    public function testRunFailsTheStatementAndUndoesIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query("CREATE TRIGGER chk BEFORE INSERT ON t FOR EACH ROW BEGIN IF NEW.a < 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'negative'; END IF; END");

        $answers = $session->run('INSERT INTO t VALUES (2), (-1)');

        $result4 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $answers[0]);
        self::assertSame([1644, 'negative', []], [$answers[0]->getCode(), $answers[0]->getMessage(), $result4->rows]);
    }

    public function testVariablesHoldTheColumnsOfARow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $variables = (new Triggers($session, $table))->variables([null, 5]);

        self::assertSame([['id', 0], ['a', 5]], array_map(static fn ($variable): array => [$variable->name, $variable->value], $variables));
    }
}
