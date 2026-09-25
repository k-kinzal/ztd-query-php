<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\LogfileGroupOptions;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlObject\StorageDefinitions;

#[CoversClass(StorageDefinitions::class)]
#[Medium]
final class StorageDefinitionsTest extends TestCase
{
    public function testWriteReturnsNullForAnotherOperation(): void
    {
        self::assertNull(StorageDefinitions::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE ts')));
    }

    #[TestWith(['mysql-8.4.7', 'CREATE TABLESPACE ts', 'CREATE TABLESPACE `ts` WAIT'])]
    #[TestWith(['mysql-8.4.7', "ALTER TABLESPACE ts ADD DATAFILE 'f' ENGINE NDB", "ALTER TABLESPACE `ts` ADD DATAFILE 'f' ENGINE = `NDB` WAIT"])]
    #[TestWith(['mysql-8.4.7', "ALTER TABLESPACE ts DROP DATAFILE 'f'", "ALTER TABLESPACE `ts` DROP DATAFILE 'f' WAIT"])]
    #[TestWith(['mysql-5.7.44', "ALTER TABLESPACE ts CHANGE DATAFILE 'f' AUTOEXTEND_SIZE 1", "ALTER TABLESPACE `ts` CHANGE DATAFILE 'f' AUTOEXTEND_SIZE = 1"])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLESPACE ts MAX_SIZE 1', 'ALTER TABLESPACE `ts` MAX_SIZE = 1 WAIT'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLESPACE a RENAME TO b', 'ALTER TABLESPACE `a` RENAME TO `b`'])]
    #[TestWith(['mysql-5.6.51', 'ALTER TABLESPACE a READ_ONLY', 'ALTER TABLESPACE `a` READ_ONLY'])]
    #[TestWith(['mysql-8.4.7', "CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'", "CREATE UNDO TABLESPACE `u` ADD DATAFILE 'u.ibu'"])]
    #[TestWith(['mysql-8.4.7', 'ALTER UNDO TABLESPACE u SET INACTIVE', 'ALTER UNDO TABLESPACE `u` SET INACTIVE'])]
    #[TestWith(['mysql-5.7.44', "CREATE LOGFILE GROUP lg ADD REDOFILE 'r'", "CREATE LOGFILE GROUP `lg` ADD REDOFILE 'r' WAIT"])]
    #[TestWith(['mysql-8.4.7', "ALTER LOGFILE GROUP lg ADD UNDOFILE 'u' INITIAL_SIZE 1K", "ALTER LOGFILE GROUP `lg` ADD UNDOFILE 'u' INITIAL_SIZE = 1024 WAIT"])]
    public function testWriteRoundTripsEveryStorageForm(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(Tree::class, StorageDefinitions::write($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($statement::class, $binder->bind($expected)::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testTablespaceWritesEveryOptionInOrder(): void
    {
        $parts = StorageDefinitions::tablespace(new TablespaceOptions(1, 2, 3, 4, 5, 6, 'NDB', 'c', StorageEncryption::Enabled, '{}', CompletionWait::NoWait));
        self::assertSame("INITIAL_SIZE = 1 AUTOEXTEND_SIZE = 2 MAX_SIZE = 3 EXTENT_SIZE = 4 FILE_BLOCK_SIZE = 5 NODEGROUP = 6 ENGINE = `NDB` COMMENT = 'c' ENGINE_ATTRIBUTE = '{}' ENCRYPTION = 'Y' NO_WAIT", (new Tree('options', $parts))->toString());
    }

    public function testChangesEndsWithTheCompletionRequest(): void
    {
        $parts = StorageDefinitions::changes(new TablespaceChanges(encryption: StorageEncryption::Disabled));
        self::assertSame("ENCRYPTION = 'N' WAIT", (new Tree('options', $parts))->toString());
    }

    public function testLogfileGroupWritesBufferSizes(): void
    {
        $parts = StorageDefinitions::logfileGroup(new LogfileGroupOptions(undoBufferSize: 1, redoBufferSize: 2, comment: 'x'));
        self::assertSame("UNDO_BUFFER_SIZE = 1 REDO_BUFFER_SIZE = 2 COMMENT = 'x' WAIT", (new Tree('options', $parts))->toString());
    }

    public function testSizesSkipsOmittedOptions(): void
    {
        self::assertCount(1, StorageDefinitions::sizes(['A' => null, 'B' => 0]));
    }

    public function testTextsAppendsTheEncryptionFlag(): void
    {
        self::assertCount(4, StorageDefinitions::texts(['COMMENT' => '', 'ENGINE_ATTRIBUTE' => null], StorageEncryption::Enabled));
    }

    public function testEngineIsOmittedWithoutASelection(): void
    {
        self::assertSame([], StorageDefinitions::engine(null));
        self::assertCount(2, StorageDefinitions::engine('NDB'));
    }

    public function testNameQuotesTheIdentifier(): void
    {
        self::assertSame('`a``b`', StorageDefinitions::name('a`b')->toString());
    }

    public function testTextEscapesQuotesAndBackslashes(): void
    {
        self::assertSame("'a''b\\\\c'", StorageDefinitions::text("a'b\\c")->toString());
    }

    #[TestWith(['mysql-8.4.7', "CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' USE LOGFILE GROUP g FILE_BLOCK_SIZE = 8192 ENGINE = InnoDB", "CREATE TABLESPACE `ts` ADD DATAFILE 'a.ibd' USE LOGFILE GROUP `g` FILE_BLOCK_SIZE = 8192 ENGINE = `InnoDB` WAIT"])]
    #[TestWith(['mysql-8.4.7', "ALTER TABLESPACE ts DROP DATAFILE 'a.ibd' INITIAL_SIZE 1M ENGINE ndb", "ALTER TABLESPACE `ts` DROP DATAFILE 'a.ibd' INITIAL_SIZE = 1048576 ENGINE = `ndb` WAIT"])]
    #[TestWith(['mysql-5.7.44', "ALTER TABLESPACE ts CHANGE DATAFILE 'a.ibd' INITIAL_SIZE 1M AUTOEXTEND_SIZE 4M MAX_SIZE 9M", "ALTER TABLESPACE `ts` CHANGE DATAFILE 'a.ibd' INITIAL_SIZE = 1048576 AUTOEXTEND_SIZE = 4194304 MAX_SIZE = 9437184"])]
    #[TestWith(['mysql-8.4.7', "CREATE LOGFILE GROUP g ADD UNDOFILE 'u' INITIAL_SIZE 1M UNDO_BUFFER_SIZE 2M REDO_BUFFER_SIZE 3M NODEGROUP 1 WAIT ENGINE ndb", "CREATE LOGFILE GROUP `g` ADD UNDOFILE 'u' INITIAL_SIZE = 1048576 UNDO_BUFFER_SIZE = 2097152 REDO_BUFFER_SIZE = 3145728 NODEGROUP = 1 ENGINE = `ndb` WAIT"])]
    #[TestWith(['mysql-8.4.7', "ALTER TABLESPACE ts ENGINE_ATTRIBUTE '{}' ENCRYPTION 'Y'", "ALTER TABLESPACE `ts` ENGINE_ATTRIBUTE = '{}' ENCRYPTION = 'Y' WAIT"])]
    public function testWriteSpellsEveryStorageClause(string $version, string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql)));
    }
}
