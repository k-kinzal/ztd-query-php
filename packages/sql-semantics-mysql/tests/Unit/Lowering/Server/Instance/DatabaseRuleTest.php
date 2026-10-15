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

    public function testOptionsLowersTheList(): void
    {
        self::assertSame('CREATE DATABASE d CHARACTER SET latin1 COLLATE latin1_bin', (new Semantics(Dialect::MySql))->analyze('create database d charset latin1 collate latin1_bin')->toString());
    }

    public function testOptionLowersCollateDefaultOf5x(): void
    {
        self::assertSame('ALTER DATABASE d COLLATE DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('alter database d collate default')->toString());
    }

    public function testReadOnlyLowersDefault(): void
    {
        self::assertSame('ALTER DATABASE d READ ONLY DEFAULT', (new Semantics(Dialect::MySql))->analyze('alter database d read only = default')->toString());
    }

    public function testReadOnlyRefusesAnInvalidValueBeforePublishingAnOption(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\AnalysisException::class);
        $this->expectExceptionMessage('READ ONLY accepts 0, 1 or DEFAULT');

        (new Semantics(Dialect::MySql))->analyze("ALTER DATABASE d READ ONLY 2 ENCRYPTION 'N'");
    }

    public function testEncryptionLowersTheValue(): void
    {
        self::assertSame("CREATE DATABASE d DEFAULT ENCRYPTION 'Y'", (new Semantics(Dialect::MySql))->analyze("create database d encryption 'Y'")->toString());
        $operation = (new Semantics(Dialect::MySql))->analyze("ALTER DATABASE d ENCRYPTION 'n\\0ignored'");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption::class, $operation->statement->options[0]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Enabled, $operation->statement->options[0]->encryption);
        self::assertSame("n\0ignored", $operation->statement->options[0]->spelling?->value);
    }

    public function testEncryptionRefusesAnInvalidValueBeforePublishingAnOption(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Incorrect argument (should be Y or N) value: 'bad'");

        (new Semantics(Dialect::MySql))->analyze("ALTER DATABASE d ENCRYPTION 'bad'");
    }
}
