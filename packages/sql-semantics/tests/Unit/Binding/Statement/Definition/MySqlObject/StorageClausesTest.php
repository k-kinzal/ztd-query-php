<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\StorageClauses;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StorageClauses::class)]
#[Medium]
final class StorageClausesTest extends TestCase
{
    public function testReadKeepsTheLastRepeatableValue(): void
    {
        $source = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLESPACE ts INITIAL_SIZE 1 INITIAL_SIZE 2K, NODEGROUP 3 COMMENT 'c' ENCRYPTION 'n' ENGINE_ATTRIBUTE '[]' STORAGE ENGINE NDB NO_WAIT")->source;
        $clauses = StorageClauses::read($source, new Identifiers(Dialect::MySql));
        self::assertSame(['INITIAL_SIZE' => 2048, 'NODEGROUP' => 3], $clauses->numbers);
        self::assertSame(['c', StorageEncryption::Disabled, '[]', 'NDB', CompletionWait::NoWait], [$clauses->comment, $clauses->encryption, $clauses->engineAttribute, $clauses->engine, $clauses->waiting]);
    }

    #[TestWith(['mysql-5.7.44', 'CREATE TABLESPACE ts ADD DATAFILE \'a\' NODEGROUP 1 NODEGROUP 2'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLESPACE ts COMMENT \'a\' COMMENT \'b\''])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLESPACE ts FILE_BLOCK_SIZE 1 FILE_BLOCK_SIZE 1'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLESPACE ts ENCRYPTION \'yes\''])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLESPACE ts ENGINE_ATTRIBUTE \'{\''])]
    #[TestWith(['mysql-8.4.7', 'CREATE LOGFILE GROUP lg ADD UNDOFILE \'u\' NODEGROUP 1.5'])]
    public function testReadRejectsRequestsTheServerRefuses(string $version, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::StorageValue->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
    }

    #[TestWith(['INITIAL_SIZE 7', 7])]
    #[TestWith(['INITIAL_SIZE 0x10', 16])]
    #[TestWith(['INITIAL_SIZE 3k', 3072])]
    #[TestWith(['INITIAL_SIZE `2M`', 2097152])]
    #[TestWith(['INITIAL_SIZE 2147483647G', 2305843008139952128])]
    public function testSizeReadsNumbersAndMultiples(string $option, int $bytes): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLESPACE ts ' . $option);
        $clauses = StorageClauses::read($statement->source, new Identifiers(Dialect::MySql));
        self::assertSame($bytes, $clauses->number('INITIAL_SIZE'));
    }

    #[TestWith(['INITIAL_SIZE 2147483648K'])]
    #[TestWith(['INITIAL_SIZE 1T'])]
    #[TestWith(['INITIAL_SIZE size'])]
    #[TestWith(['INITIAL_SIZE 18446744073709551615'])]
    public function testSizeRejectsUnusableSizes(string $option): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLESPACE ts ' . $option);
    }

    public function testEncryptionAcceptsEitherCase(): void
    {
        $source = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->source;
        self::assertSame(StorageEncryption::Enabled, StorageClauses::encryption('y', $source));
        self::assertNull(StorageClauses::encryption(null, $source));
        $this->expectException(InvalidSql::class);
        StorageClauses::encryption('', $source);
    }

    public function testNumberReturnsNullForAnOmittedOption(): void
    {
        $clauses = new StorageClauses(['MAX_SIZE' => 5], null, null, null, null, CompletionWait::Wait);
        self::assertSame(5, $clauses->number('MAX_SIZE'));
        self::assertNull($clauses->number('INITIAL_SIZE'));
    }

    public function testTablespaceCarriesEveryInitialOption(): void
    {
        $options = (new StorageClauses(['INITIAL_SIZE' => 1, 'AUTOEXTEND_SIZE' => 2, 'MAX_SIZE' => 3, 'EXTENT_SIZE' => 4, 'FILE_BLOCK_SIZE' => 5, 'NODEGROUP' => 6], 'c', StorageEncryption::Enabled, '{}', 'NDB', CompletionWait::NoWait))->tablespace();
        self::assertSame([1, 2, 3, 4, 5, 6, 'c', 'NDB'], [$options->initialSize, $options->autoextendSize, $options->maxSize, $options->extentSize, $options->fileBlockSize, $options->nodegroup, $options->comment, $options->engine]);
    }

    public function testChangesCarriesTheChangeableOptions(): void
    {
        $changes = (new StorageClauses(['INITIAL_SIZE' => 1, 'MAX_SIZE' => 3], null, StorageEncryption::Disabled, null, null, CompletionWait::NoWait))->changes();
        self::assertSame([1, null, 3, StorageEncryption::Disabled, CompletionWait::NoWait], [$changes->initialSize, $changes->autoextendSize, $changes->maxSize, $changes->encryption, $changes->waiting]);
    }

    public function testLogfileGroupCarriesTheBufferSizes(): void
    {
        $options = (new StorageClauses(['UNDO_BUFFER_SIZE' => 8, 'REDO_BUFFER_SIZE' => 9], 'c', null, null, 'NDB', CompletionWait::Wait))->logfileGroup();
        self::assertSame([8, 9, 'c', 'NDB'], [$options->undoBufferSize, $options->redoBufferSize, $options->comment, $options->engine]);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerReadSpellsEveryStorageOption')]
    public function testReadSpellsEveryStorageOption(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerReadSpellsEveryStorageOption(): iterable
    {
        return [
            'create tablespace ts add datafile \'a.ibd\' initial_size = 10M autoextend_size = 4m max_size 1G extent... 0' => [Dialect::MySql, null, [], 'create tablespace ts add datafile \'a.ibd\' initial_size = 10M autoextend_size = 4m max_size 1G extent_size = 64K file_block_size = 8k engine innodb', 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 10485760 AUTOEXTEND_SIZE = 4194304 MAX_SIZE = 1073741824 EXTENT_SIZE = 65536 FILE_BLOCK_SIZE = 8192 ENGINE = `innodb` WAIT'],
            'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 1024 (MySql)' => [Dialect::MySql, null, [], 'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 1024', 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 1024 WAIT'],
            'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 0010M (MySql)' => [Dialect::MySql, null, [], 'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 0010M', 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 10485760 WAIT'],
            'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 2g (MySql)' => [Dialect::MySql, null, [], 'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 2g', 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 2147483648 WAIT'],
            'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 2147483647K (MySql)' => [Dialect::MySql, null, [], 'CREATE TABLESPACE ts ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 2147483647K', 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' INITIAL_SIZE = 2199023254528 WAIT'],
            'create tablespace ts add datafile \'a.ibd\' encryption = \'y\' comment = \'c\' engine_attribute = \'{"a":1}\' (MySql)' => [Dialect::MySql, null, [], 'create tablespace ts add datafile \'a.ibd\' encryption = \'y\' comment = \'c\' engine_attribute = \'{"a":1}\'', 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' COMMENT = \'c\' ENGINE_ATTRIBUTE = \'{"a":1}\' ENCRYPTION = \'Y\' WAIT'],
            'create logfile group g add undofile \'u\' initial_size 1M undo_buffer_size 2M redo_buffer_size 3M node... 6' => [Dialect::MySql, null, [], 'create logfile group g add undofile \'u\' initial_size 1M undo_buffer_size 2M redo_buffer_size 3M nodegroup 1 comment \'x\' engine ndb', 'CREATE LOGFILE GROUP `g` ADD UNDOFILE \'u\' INITIAL_SIZE = 1048576 UNDO_BUFFER_SIZE = 2097152 REDO_BUFFER_SIZE = 3145728 NODEGROUP = 1 ENGINE = `ndb` COMMENT = \'x\' WAIT'],
            'alter tablespace ts add datafile \'b\' initial_size 1m engine innodb (MySql)' => [Dialect::MySql, null, [], 'alter tablespace ts add datafile \'b\' initial_size 1m engine innodb', 'ALTER TABLESPACE `ts` ADD DATAFILE \'b\' INITIAL_SIZE = 1048576 ENGINE = `innodb` WAIT'],
        ];
    }

    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' INITIAL_SIZE = 10X"])]
    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' INITIAL_SIZE = x10M"])]
    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' INITIAL_SIZE = 10MB"])]
    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' INITIAL_SIZE = 2147483648K"])]
    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' ENGINE_ATTRIBUTE = 'nope'"])]
    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' ENCRYPTION = 'x'"])]
    #[TestWith(["CREATE TABLESPACE ts ADD DATAFILE 'a.ibd' COMMENT = 'a' COMMENT = 'b'"])]
    #[TestWith(["CREATE LOGFILE GROUP g ADD UNDOFILE 'u' NODEGROUP 1 NODEGROUP 2 ENGINE ndb"])]
    public function testReadRejectsOutOfDomainStorageValues(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
