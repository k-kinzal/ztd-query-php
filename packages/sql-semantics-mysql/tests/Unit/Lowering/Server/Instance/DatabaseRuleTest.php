<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\DatabaseRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DatabaseRule::class)]
#[Medium]
final class DatabaseRuleTest extends TestCase
{
    public function testStatementLowersDrop(): void
    {
        self::assertSame('DROP DATABASE d', (new Inspection('mysql-8.4.7'))->inspect('drop database d')->toString());
    }

    public function testOptionsLowersTheList(): void
    {
        self::assertSame('CREATE DATABASE d CHARACTER SET latin1 COLLATE latin1_bin', (new Inspection())->inspect('create database d charset latin1 collate latin1_bin')->toString());
    }

    public function testOptionLowersCollateDefaultOf5x(): void
    {
        self::assertSame('ALTER DATABASE d COLLATE DEFAULT', (new Inspection('mysql-5.7.44'))->inspect('alter database d collate default')->toString());
    }

    public function testReadOnlyLowersDefault(): void
    {
        self::assertSame('ALTER DATABASE d READ ONLY DEFAULT', (new Inspection())->inspect('alter database d read only = default')->toString());
    }

    public function testEncryptionLowersTheValue(): void
    {
        self::assertSame("CREATE DATABASE d DEFAULT ENCRYPTION 'Y'", (new Inspection())->inspect("create database d encryption 'Y'")->toString());
    }
}
