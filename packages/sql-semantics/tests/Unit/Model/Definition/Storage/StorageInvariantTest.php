<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StorageInvariant::class)]
#[Medium]
final class StorageInvariantTest extends TestCase
{
    public function testNamesAcceptsAbsentOptionalNames(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        StorageInvariant::names($origin, 'ts', null);
        $this->expectException(InvalidStructure::class);
        StorageInvariant::names($origin, 'ts', '');
    }

    public function testNamesRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        StorageInvariant::names($origin, 'ts');
    }

    public function testQuantitiesRejectsANegativeCount(): void
    {
        StorageInvariant::quantities(0, null, 7);
        $this->expectException(InvalidStructure::class);
        StorageInvariant::quantities(1, -1);
    }

    public function testEngineRejectsInvalidJsonAttributes(): void
    {
        StorageInvariant::engine('InnoDB', '');
        StorageInvariant::engine(null, '{"a": [1]}');
        $this->expectException(InvalidStructure::class);
        StorageInvariant::engine(null, 'not json');
    }

    public function testLegacyDistinguishesMySqlFiveGrammars(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $modern = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        self::assertTrue(StorageInvariant::legacy($legacy));
        self::assertFalse(StorageInvariant::legacy($modern));
    }

    public function testModernRejectsALegacyRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        StorageInvariant::modern($origin, 'An undo tablespace');
    }

    public function testOlderRejectsAModernRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        StorageInvariant::older($origin, 'CHANGE DATAFILE');
    }

    public function testOptionsRejectsEncryptionBeforeMySqlEight(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        StorageInvariant::options($origin, new TablespaceOptions(fileBlockSize: 8192));
        $this->expectException(InvalidStructure::class);
        StorageInvariant::options($origin, new TablespaceChanges(encryption: StorageEncryption::Enabled));
    }

    public function testOptionsRejectsFileBlockSizeInMySqlFiveSix(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        StorageInvariant::options($origin, new TablespaceOptions(fileBlockSize: 8192));
    }
}
