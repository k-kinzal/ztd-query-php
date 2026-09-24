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
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\CreateTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTablespaceStatement::class)]
#[Medium]
final class CreateTablespaceStatementTest extends TestCase
{
    public function testWithNameKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        $changed = $statement->withName('other');
        self::assertSame('ts', $statement->name);
        self::assertSame("CREATE TABLESPACE `other` ADD DATAFILE 'ts.ibd' WAIT", $changed->toString());
    }

    public function testWithDatafileRemovesTheFileOnlyFromMySqlEight(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        self::assertNull($statement->withDatafile(null)->datafile);
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $legacy);
        $this->expectException(InvalidStructure::class);
        $legacy->withDatafile(null);
    }

    public function testWithLogfileGroupAddsAndRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLESPACE ts');
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        self::assertSame('CREATE TABLESPACE `ts` USE LOGFILE GROUP `lg` WAIT', $statement->withLogfileGroup('lg')->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withLogfileGroup('');
    }

    public function testWithOptionsReplacesTheProperties(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'a' COMMENT 'x'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        $changed = $statement->withOptions(new TablespaceOptions(initialSize: 1024, encryption: StorageEncryption::Enabled));
        self::assertSame('x', $statement->options->comment);
        self::assertSame("CREATE TABLESPACE `ts` ADD DATAFILE 'a' INITIAL_SIZE = 1024 ENCRYPTION = 'Y' WAIT", $changed->toString());
    }

    public function testWithOptionsRejectsEncryptionBeforeMySqlEight(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'a'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new TablespaceOptions(encryption: StorageEncryption::Enabled));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testWithOriginPreservesTheDefinition(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'a' ENGINE NDB");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
