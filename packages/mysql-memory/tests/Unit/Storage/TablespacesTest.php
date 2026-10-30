<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Storage\Tablespaces;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Tablespaces::class)]
#[Small]
final class TablespacesTest extends TestCase
{
    public function testMissingDistinguishesSelectedPartitionFiles(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; ALTER TABLE p DISCARD PARTITION p0 TABLESPACE');
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        $storage = new Tablespaces();
        self::assertTrue($storage->missing($table));
        self::assertTrue($storage->missing($table, [0]));
        self::assertFalse($storage->missing($table, [1]));
        self::assertFalse($storage->missing($table, []));
    }

    public function testOpenedWarnsOnlyWhenTheHandleIsCold(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE; FLUSH TABLES');
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        (new Tablespaces())->opened($table, $session);
        self::assertSame([['Warning', 1814, "InnoDB: Tablespace has been discarded for table 't'"]], $session->diagnostics->conditions);
        $session->instance->dictionary->cache->open('d', 't');
        $session->diagnostics->clear();
        (new Tablespaces())->opened($table, $session);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testReadRefusesDetachedStorageEvenAfterItsRowsAreRemoved(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame([], $table->data->rows);
        $this->expectExceptionCode(1814);
        (new Tablespaces())->read($table, GrammarRelease::MySql847);
    }

    public function testWriteChecksTheRowsDestinationPartition(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; ALTER TABLE p DISCARD PARTITION p0 TABLESPACE');
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        (new Tablespaces())->write($table, [1], $context);
        $this->expectExceptionCode(1814);
        (new Tablespaces())->write($table, [0], $context);
    }

    public function testDiscardKeepsTheOtherPartitionsRowsAndDefinition(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; INSERT INTO p VALUES(0),(1)');
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        $definition = $table->definition;
        (new Tablespaces())->discard($table, [0], new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        self::assertSame($definition, $table->definition);
        self::assertSame([[1]], array_values($table->data->rows));
        self::assertSame(['p0' => true], $table->discardedTablespaces);
    }

    public function testAlterKeepsLegacyChangesOverDetachedFiles(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE; ALTER TABLE t ADD c INT');
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame(1, $table->definition->position('c'));
        self::assertSame(['' => true], $table->discardedTablespaces);
    }

    public function testAlterRequiresTheFileOnModernServers(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $this->expectExceptionCode(1814);
        $session->query('ALTER TABLE t ADD c INT');
    }

    public function testDiscardKeepsTheAutoIncrementCounter(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT AUTO_INCREMENT PRIMARY KEY); INSERT INTO t VALUES(15); ALTER TABLE t DISCARD TABLESPACE');
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame(16, $table->data->autoIncrement);
    }

    public function testDiscardDetachesEveryKeyPartitionWithoutReadingItsRows(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY KEY(id) PARTITIONS 2; INSERT INTO p VALUES(0),(1); ALTER TABLE p DISCARD TABLESPACE');
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame([], $table->data->rows);
        self::assertSame(['p0' => true, 'p1' => true], $table->discardedTablespaces);
    }

    public function testTruncatePreservesTheLegacyWriteConditions(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $session->run('TRUNCATE t');
        self::assertSame([1814, 1814, 1812], array_column($session->diagnostics->conditions, 1));
        $session->run('SELECT * FROM t');
        self::assertSame([1814, 1146], array_column($session->diagnostics->conditions, 1));
    }

    public function testMessagePreservesTheReleaseWording(): void
    {
        $storage = new Tablespaces();
        self::assertSame("Tablespace has been discarded for table 't'", $storage->message('t', GrammarRelease::MySql847));
        self::assertSame("Tablespace is discarded for table, 't'", $storage->message('t', GrammarRelease::MySql910));
    }

    public function testAlterKeepsModernIndexChangesOverDetachedFiles(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE; ALTER TABLE t ADD INDEX(id)');
        self::assertSame([1814], array_column($session->diagnostics->conditions, 1));
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertCount(1, $table->definition->keys);
        self::assertSame(['' => true], $table->discardedTablespaces);
    }

    public function testAlterCannotCopyDetachedRowsToAnotherEngine(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $session->run('ALTER TABLE t ENGINE=MyISAM');
        self::assertSame([1814, 1146], array_column($session->diagnostics->conditions, 1));
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame('InnoDB', $table->definition->engine);
    }
}
