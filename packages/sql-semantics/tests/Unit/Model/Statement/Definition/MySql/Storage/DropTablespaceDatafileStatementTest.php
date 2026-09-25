<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropTablespaceDatafileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTablespaceDatafileStatement::class)]
#[Medium]
final class DropTablespaceDatafileStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testWithOriginPreservesTheAlteration(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("ALTER TABLESPACE ts DROP DATAFILE 'f.ibd' INITIAL_SIZE 2K");
        self::assertInstanceOf(DropTablespaceDatafileStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame(2048, $copy->changes->initialSize);
        self::assertSame("ALTER TABLESPACE `ts` DROP DATAFILE 'f.ibd' INITIAL_SIZE = 2048 WAIT", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER TABLESPACE ts DROP DATAFILE 'f.ibd'");
        self::assertInstanceOf(DropTablespaceDatafileStatement::class, $statement);
        self::assertSame('t2', $statement->withName('t2')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithDatafileKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER TABLESPACE ts DROP DATAFILE 'f.ibd'");
        self::assertInstanceOf(DropTablespaceDatafileStatement::class, $statement);
        self::assertSame("it's.ibd", $statement->withDatafile("it's.ibd")->datafile);
        self::assertSame('f.ibd', $statement->datafile);
    }

    public function testWithChangesRejectsEncryptionBeforeMySqlEight(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("ALTER TABLESPACE ts DROP DATAFILE 'f.ibd'");
        self::assertInstanceOf(DropTablespaceDatafileStatement::class, $statement);
        self::assertSame(1, $statement->withChanges(new TablespaceChanges(maxSize: 1))->changes->maxSize);
        $this->expectException(InvalidStructure::class);
        $statement->withChanges(new TablespaceChanges(encryption: StorageEncryption::Enabled));
    }
}
