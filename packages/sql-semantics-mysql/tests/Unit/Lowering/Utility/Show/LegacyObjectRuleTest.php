<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\LegacyObjectRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LegacyObjectRule::class)]
#[Medium]
final class LegacyObjectRuleTest extends TestCase
{
    public function testStatementLowersEveryKind(): void
    {
        self::assertSame('SHOW PRIVILEGES', (new Inspection('mysql-5.6.51'))->inspect('show privileges')->toString());
    }

    public function testSchemaLowersTheSchemaStatements(): void
    {
        self::assertSame('SHOW CREATE TRIGGER db.t', (new Inspection('mysql-5.6.51'))->inspect('show create trigger db.t')->toString());
        self::assertSame('SHOW OPEN TABLES', (new Inspection('mysql-5.7.44'))->inspect('show open tables')->toString());
    }

    public function testColumnsLowersShowColumns(): void
    {
        self::assertSame('SHOW FULL COLUMNS FROM t', (new Inspection('mysql-5.7.44'))->inspect('show full fields from t')->toString());
    }

    public function testKeysLowersShowIndex(): void
    {
        self::assertSame('SHOW INDEXES FROM t', (new Inspection('mysql-5.6.51'))->inspect('show keys from t')->toString());
    }

    public function testServerLowersTheServerStatements(): void
    {
        self::assertSame('SHOW GRANTS FOR u', (new Inspection('mysql-5.7.44'))->inspect('show grants for u')->toString());
        self::assertSame('SHOW FULL PROCESSLIST', (new Inspection('mysql-5.6.51'))->inspect('show full processlist')->toString());
    }

    public function testEnginesLowersShowEngines(): void
    {
        self::assertSame('SHOW ENGINES', (new Inspection('mysql-5.6.51'))->inspect('show engines')->toString());
    }

    public function testCreateUserLowersShowCreateUser(): void
    {
        self::assertSame('SHOW CREATE USER u@h', (new Inspection('mysql-5.7.44'))->inspect('show create user u@h')->toString());
    }

    public function testEngineLowersTheReport(): void
    {
        self::assertSame('SHOW ENGINE innodb MUTEX', (new Inspection('mysql-5.7.44'))->inspect('show engine innodb mutex')->toString());
    }
}
