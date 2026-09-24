<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
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
}
