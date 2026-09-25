<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\AccountSettings;

#[CoversClass(AccountSettings::class)]
#[Medium]
final class AccountSettingsTest extends TestCase
{
    public function testWriteRetainsTheConcreteRequestOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD='new'", strict: false);
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        self::assertSame("SET PASSWORD = 'new'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['mysql-8.4.7', 'SET ROLE ALL', 'SET ROLE ALL'])]
    #[TestWith(['mysql-8.4.7', 'SET ROLE r', "SET ROLE 'r'"])]
    #[TestWith(['mysql-8.4.7', 'SET ROLE ALL EXCEPT r', "SET ROLE ALL EXCEPT 'r'"])]
    #[TestWith(['mysql-8.4.7', 'SET DEFAULT ROLE ALL TO u', "SET DEFAULT ROLE ALL TO 'u'"])]
    #[TestWith(['mysql-8.4.7', 'SET DEFAULT ROLE r TO u', "SET DEFAULT ROLE 'r' TO 'u'"])]
    #[TestWith(['mysql-8.4.7', "SET PASSWORD FOR u = 'x'", "SET PASSWORD FOR 'u' = 'x'"])]
    #[TestWith(['mysql-8.4.7', 'SET PASSWORD TO RANDOM', 'SET PASSWORD TO RANDOM'])]
    #[TestWith(['mysql-5.6.51', "SET PASSWORD = '*abc'", "SET PASSWORD = '*abc'"])]
    #[TestWith(['mysql-5.6.51', "SET PASSWORD = PASSWORD('x')", "SET PASSWORD = PASSWORD('x')"])]
    #[TestWith(['mysql-5.6.51', "SET PASSWORD = 'x', @a = 1", "SET PASSWORD = 'x', @`a` = 1"])]
    public function testWriteRoutesRoleAndPasswordSettings(string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ConfigurationStatement::class, $statement);
        self::assertSame($expected, AccountSettings::write($statement)?->toString());
    }
}
