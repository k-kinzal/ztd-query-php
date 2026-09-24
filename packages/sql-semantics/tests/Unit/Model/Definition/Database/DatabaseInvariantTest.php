<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\CurrentDatabase;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Statement\Origin;
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

    /**
     * @return iterable<string, array{string, list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly>, bool}>
     */
    public static function providerOptionsAcceptsTheReleaseDomain(): iterable
    {
        return [
            'no options when creating' => ['mysql-8.4.7', [], true],
            'character set when creating' => ['mysql-8.4.7', [new DatabaseCharacterSet('utf8mb4')], true],
            'collation when creating' => ['mysql-8.4.7', [new DatabaseCollation('utf8mb4_bin')], true],
            'encryption when creating' => ['mysql-8.4.7', [DatabaseEncryption::Enabled], true],
            'read only when altering' => ['mysql-8.4.7', [DatabaseReadOnly::Enabled], false],
            'repeated agreeing read only' => ['mysql-8.4.7', [DatabaseReadOnly::Enabled, DatabaseReadOnly::Enabled], false],
            'legacy character set' => ['mysql-5.7.44', [new DatabaseCharacterSet('utf8')], false],
            'legacy inherited character set' => ['mysql-5.7.44', [new DatabaseCharacterSet(ServerCharacterInheritance::Inherit)], true],
            'legacy inherited collation' => ['mysql-5.6.51', [new DatabaseCollation(ServerCharacterInheritance::Inherit)], false],
        ];
    }

    /**
     * @param list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly> $options
     */
    #[DataProvider('providerOptionsAcceptsTheReleaseDomain')]
    public function testOptionsAcceptsTheReleaseDomain(string $version, array $options, bool $creating): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectNotToPerformAssertions();
        DatabaseInvariant::options($origin, $options, $creating);
    }

    /**
     * @return iterable<string, array{string, list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly>, bool}>
     */
    public static function providerOptionsRejectsOutsideTheReleaseDomain(): iterable
    {
        return [
            'no options when altering' => ['mysql-8.4.7', [], false],
            'read only when creating' => ['mysql-8.4.7', [DatabaseReadOnly::Enabled], true],
            'legacy read only' => ['mysql-5.7.44', [DatabaseReadOnly::Enabled], false],
            'legacy encryption' => ['mysql-5.7.44', [DatabaseEncryption::Disabled], false],
            'inherited character set' => ['mysql-8.0.44', [new DatabaseCharacterSet(ServerCharacterInheritance::Inherit)], true],
            'inherited collation' => ['mysql-9.1.0', [new DatabaseCollation(ServerCharacterInheritance::Inherit)], false],
            'contradicting read only after encryption' => ['mysql-8.4.7', [DatabaseEncryption::Enabled, DatabaseReadOnly::Disabled, DatabaseReadOnly::Enabled], false],
        ];
    }

    /**
     * @param list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly> $options
     */
    #[DataProvider('providerOptionsRejectsOutsideTheReleaseDomain')]
    public function testOptionsRejectsOutsideTheReleaseDomain(string $version, array $options, bool $creating): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options($origin, $options, $creating);
    }

    public function testOptionsAcceptsInheritanceWithoutAKnownRelease(): void
    {
        $origin = new Origin('s', new Node('statement', 0, []), Dialect::MySql);
        $this->expectNotToPerformAssertions();
        DatabaseInvariant::options($origin, [new DatabaseCharacterSet(ServerCharacterInheritance::Inherit), DatabaseEncryption::Enabled], true);
    }

    public function testTargetAcceptsANamedOrCurrentMySqlDatabase(): void
    {
        $origin = new Origin('s', new Node('statement', 0, []), Dialect::MySql);
        $this->expectNotToPerformAssertions();
        DatabaseInvariant::target($origin, 'app');
        DatabaseInvariant::target($origin, CurrentDatabase::Session);
    }

    public function testTargetRejectsAnEmptyName(): void
    {
        $origin = new Origin('s', new Node('statement', 0, []), Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::target($origin, '');
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testUpgradeAcceptsALegacyRelease(string $version): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectNotToPerformAssertions();
        DatabaseInvariant::upgrade($origin, 'legacy');
    }

    public function testUpgradeAcceptsAnUnknownRelease(): void
    {
        $origin = new Origin('s', new Node('statement', 0, []), Dialect::MySql);
        $this->expectNotToPerformAssertions();
        DatabaseInvariant::upgrade($origin, 'legacy');
    }

    public function testUpgradeRejectsAnotherDatabaseLanguage(): void
    {
        $origin = new Origin('s', new Node('statement', 0, []), Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::upgrade($origin, 'legacy');
    }
}
