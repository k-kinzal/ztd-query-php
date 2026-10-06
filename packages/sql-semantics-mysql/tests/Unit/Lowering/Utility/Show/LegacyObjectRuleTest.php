<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\LegacyObjectRule;

#[CoversClass(LegacyObjectRule::class)]
#[Medium]
final class LegacyObjectRuleTest extends TestCase
{
    public function testStatementLowersEveryKind(): void
    {
        self::assertSame('SHOW PRIVILEGES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show privileges')->toString());
    }

    public function testSchemaLowersTheSchemaStatements(): void
    {
        self::assertSame('SHOW CREATE TRIGGER db.t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show create trigger db.t')->toString());
        self::assertSame('SHOW OPEN TABLES', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show open tables')->toString());
    }

    public function testColumnsLowersShowColumns(): void
    {
        self::assertSame('SHOW FULL COLUMNS FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show full fields from t')->toString());
    }

    public function testKeysLowersShowIndex(): void
    {
        self::assertSame('SHOW INDEXES FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show keys from t')->toString());
    }

    public function testServerLowersTheServerStatements(): void
    {
        self::assertSame('SHOW GRANTS FOR u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show grants for u')->toString());
        self::assertSame('SHOW FULL PROCESSLIST', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show full processlist')->toString());
    }

    public function testEnginesLowersShowEngines(): void
    {
        self::assertSame('SHOW ENGINES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show engines')->toString());
    }

    public function testCreateUserLowersShowCreateUser(): void
    {
        self::assertSame('SHOW CREATE USER u@h', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show create user u@h')->toString());
    }

    public function testEngineLowersTheReport(): void
    {
        self::assertSame('SHOW ENGINE innodb MUTEX', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show engine innodb mutex')->toString());
    }
}
