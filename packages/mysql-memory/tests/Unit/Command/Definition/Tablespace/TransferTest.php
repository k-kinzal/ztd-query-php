<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Tablespace;

use MySqlMemory\Command\Definition\Tablespace\Transfer;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Transfer::class)]
#[Small]
final class TransferTest extends TestCase
{
    public function testExecuteDiscardsTheFileWithoutLosingTheDefinition(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); INSERT INTO t VALUES(1); ALTER TABLE t DISCARD TABLESPACE');
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame([], $table->data->rows);
        self::assertSame(['' => true], $table->discardedTablespaces);
        self::assertSame('id', $table->definition->columns[0]->name);
    }

    public function testExecuteRefusesLegacyPartitionHandlersWithoutDiscardingRows(): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; INSERT INTO p VALUES(0),(1)');
        $session->run('ALTER TABLE p DISCARD TABLESPACE');
        self::assertSame([1031], array_column($session->diagnostics->conditions, 1));
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame([[0], [1]], array_values($table->data->rows));
    }

    public function testModifiersRequireTheDefaultAlgorithm(): void
    {
        $this->expectExceptionCode(1845);
        (new Transfer())->modifiers([new AlgorithmOption(new Name('COPY'))], false);
    }

    public function testPartitionsValidateEveryNameBeforeChangingAnyFile(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2');
        $session->run('ALTER TABLE p DISCARD PARTITION p0,missing TABLESPACE');
        self::assertSame(1735, $session->diagnostics->conditions[0][1]);
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame([], $table->discardedTablespaces);
    }

    public function testImportRetainsTheDetachedStateAfterAFileFailure(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $session->run('ALTER TABLE t IMPORT TABLESPACE');
        self::assertSame([1810, 1816, 1812], array_column($session->diagnostics->conditions, 1));
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame(['' => true], $table->discardedTablespaces);
        $this->expectExceptionCode(1814);
        $session->query('SELECT * FROM t');
    }

    public function testPathIncludesThePartitionFileName(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2');
        $table = $session->instance->dictionary->table('d', 'p', $session->temporaries);
        self::assertNotNull($table);
        self::assertSame('d/p#p#p0', (new Transfer())->path($table, 0, $session));
    }
}
