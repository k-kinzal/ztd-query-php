<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DatabaseInvariant::class)]
#[Medium]
final class DatabaseInvariantTest extends TestCase
{
    public function testTargetRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::target($origin, 'app');
    }

    public function testOptionsRejectsEncryptionInAnOlderRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options($origin, [DatabaseEncryption::Enabled], true);
    }

    public function testOptionsRejectsLegacyCharacterInheritanceInANewerRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options($origin, [new DatabaseCollation(ServerCharacterInheritance::Inherit)], true);
    }

    public function testOptionsRejectsContradictoryAccessRequests(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options($origin, [DatabaseReadOnly::Enabled, DatabaseReadOnly::Disabled], false);
    }

    public function testUpgradeRejectsANewerRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::upgrade($origin, 'legacy');
    }
}
