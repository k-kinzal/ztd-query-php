<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Storage\References;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(References::class)]
#[Small]
final class ReferencesTest extends TestCase
{
    public function testOrphanRefusesAChildRowWithoutAParentRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (id INT PRIMARY KEY, pid INT, CONSTRAINT fk FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1452);
        $this->expectExceptionMessage('Cannot add or update a child row: a foreign key constraint fails (`d`.`c`, CONSTRAINT `fk` FOREIGN KEY (`pid`) REFERENCES `p` (`id`))');

        $session->query('INSERT INTO c VALUES (3, 3)');
    }

    public function testEnabledTellsWhetherTheSessionChecksForeignKeys(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $before = (new References($session, $context))->enabled();
        $session->query('SET foreign_key_checks = 0');

        self::assertSame([true, false], [$before, (new References($session, $context))->enabled()]);
    }

    public function testViolationDescribesTheKeyOfTheChildTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id) ON DELETE CASCADE)');
        $table = $session->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);

        $error = (new References($session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))->violation($table, $table->definition->foreignKeys[0], true);

        self::assertSame([1451, 'Cannot delete or update a parent row: a foreign key constraint fails (`d`.`c`, CONSTRAINT `c_ibfk_1` FOREIGN KEY (`pid`) REFERENCES `p` (`id`) ON DELETE CASCADE)'], [$error->getCode(), $error->getMessage()]);
    }

    public function testPositionsAnswersTheReferencedColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (x INT, id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id))');
        $parent = $session->instance->dictionary->table('d', 'p');
        $child = $session->instance->dictionary->table('d', 'c');
        self::assertNotNull($parent);
        self::assertNotNull($child);

        self::assertSame([1], (new References($session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))->positions($parent, $child->definition->foreignKeys[0]));
    }

    public function testMatchingFindsTheRowsOfEqualValues(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE p (id VARCHAR(5) PRIMARY KEY); INSERT INTO p VALUES ('a'), ('b')");
        $parent = $session->instance->dictionary->table('d', 'p');
        self::assertNotNull($parent);

        self::assertSame([2], (new References($session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))->matching($parent, [0], ['B']));
    }

    public function testKeyIsEqualForEqualValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id VARCHAR(5) PRIMARY KEY)');
        $parent = $session->instance->dictionary->table('d', 'p');
        self::assertNotNull($parent);
        $references = new References($session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame($references->key($parent, [0], ['abc']), $references->key($parent, [0], ['ABC']));
    }

    public function testChildrenAnswersTheKeysThatReferenceATable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, CONSTRAINT x FOREIGN KEY (pid) REFERENCES p(id))');
        $parent = $session->instance->dictionary->table('d', 'p');
        self::assertNotNull($parent);

        self::assertSame(['x'], array_map(static fn (array $child): string => $child[1]->name, (new References($session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))->children($parent)));
    }

    public function testDeletingCascadesThroughTheChildTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES p(id) ON DELETE CASCADE); CREATE TABLE g (id INT PRIMARY KEY, cid INT, FOREIGN KEY (cid) REFERENCES c(id) ON DELETE SET NULL); INSERT INTO p VALUES (1), (2); INSERT INTO c VALUES (10, 1), (20, 2); INSERT INTO g VALUES (100, 10), (200, 20); DELETE FROM p WHERE id = 1');

        $result1 = $session->query('SELECT * FROM c')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        $result2 = $session->query('SELECT * FROM g ORDER BY id')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([[['20', '2']], [['100', null], ['200', '20']]], [$result1->rows, $result2->rows]);
    }

    public function testUpdatingCarriesTheNewValuesToTheChildRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES p(id) ON UPDATE CASCADE); INSERT INTO p VALUES (1); INSERT INTO c VALUES (10, 1); UPDATE p SET id = 5');

        $result3 = $session->query('SELECT * FROM c')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([['10', '5']], $result3->rows);
    }

    public function testChangingRefusesSetDefaultAsRestrict(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT DEFAULT 7, FOREIGN KEY (pid) REFERENCES p(id) ON DELETE SET DEFAULT); INSERT INTO p VALUES (1), (7); INSERT INTO c VALUES (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1451);

        $session->query('DELETE FROM p WHERE id = 1');
    }

    public function testCascadeDeletesASelfReferencingTree(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, parent INT, FOREIGN KEY (parent) REFERENCES t(id) ON DELETE CASCADE); INSERT INTO t VALUES (1, NULL), (2, 1), (3, 2), (4, 1); DELETE FROM t WHERE id = 1');

        $result4 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertSame([], $result4->rows);
    }
    public function testMatchingWaitsForAReferencedRowAnotherTransactionDeleted(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p (id)); INSERT INTO p VALUES (1); BEGIN; DELETE FROM p');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1205);

        $second->query('INSERT INTO c VALUES (1)');
    }

    public function testMatchingLocksTheReferencedRowShared(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p (id)); INSERT INTO p VALUES (1); BEGIN; INSERT INTO c VALUES (1)');
        $table = $instance->dictionary->table('d', 'p');
        self::assertNotNull($table);

        self::assertTrue($instance->transactions->locks->holds($table, 1, $session->id, \MySqlMemory\Concurrency\LockMode::Shared));
    }
}
