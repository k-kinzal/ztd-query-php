<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\DatabaseRule;

#[CoversClass(DatabaseRule::class)]
#[Medium]
final class DatabaseRuleTest extends TestCase
{
    public function testStatementLowersDrop(): void
    {
        self::assertSame('DROP DATABASE d', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('drop database d')->toString());
    }

    public function testUpgradeRetainsTheDirectoryRoleAndOrdinaryNames(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.7.44');
        $statement = $semantics->analyze('ALTER DATABASE `#mysql50#old-name` UPGRADE DATA DIRECTORY NAME');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName::class, $statement->name);
        self::assertSame('old-name', $statement->name->suffix->value);
        $ordinary = $semantics->analyze('ALTER DATABASE ordinary UPGRADE DATA DIRECTORY NAME');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName::class, $ordinary);
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $ordinary->name);
        self::assertSame('ordinary', $ordinary->name->value);
        self::assertSame('Incorrect usage of ALTER DATABASE UPGRADE DATA DIRECTORY NAME and name', $ordinary->condition()?->message());
    }

    public function testOptionsLowersTheList(): void
    {
        self::assertSame('CREATE DATABASE d CHARSET latin1 COLLATE latin1_bin', (new Semantics(Dialect::MySql))->analyze('create database d charset latin1 collate latin1_bin')->toString());
    }

    public function testOptionLowersCollateDefaultOf5x(): void
    {
        self::assertSame('ALTER DATABASE d COLLATE DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('alter database d collate default')->toString());
    }

    public function testReadOnlyLowersDefault(): void
    {
        self::assertSame('ALTER DATABASE d READ ONLY = DEFAULT', (new Semantics(Dialect::MySql))->analyze('alter database d read only = default')->toString());
    }

    public function testReadOnlyRefusesAnInvalidValueBeforePublishingAnOption(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\AnalysisException::class);
        $this->expectExceptionMessage('READ ONLY accepts 0, 1 or DEFAULT');

        (new Semantics(Dialect::MySql))->analyze("ALTER DATABASE d READ ONLY 2 ENCRYPTION 'N'");
    }

    public function testEncryptionLowersTheValue(): void
    {
        self::assertSame("CREATE DATABASE d ENCRYPTION 'Y'", (new Semantics(Dialect::MySql))->analyze("create database d encryption 'Y'")->toString());
        $operation = (new Semantics(Dialect::MySql))->analyze("ALTER DATABASE d ENCRYPTION 'n\\0ignored'");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption::class, $operation->options[0]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Enabled, $operation->options[0]->encryption);
        self::assertSame("n\0ignored", $operation->options[0]->spelling?->value);
    }

    public function testEncryptionRetainsAnInvalidValueOnItsCompleteStatement(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("ALTER DATABASE d ENCRYPTION 'bad'");
        self::assertSame("ALTER DATABASE d ENCRYPTION 'bad'", $statement->toString());
        self::assertSame("Incorrect argument (should be Y or N) value: 'bad'", $statement->diagnostics()[0]->message());
    }
}
