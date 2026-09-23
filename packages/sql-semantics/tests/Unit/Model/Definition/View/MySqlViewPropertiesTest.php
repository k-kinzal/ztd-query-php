<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlViewProperties::class)]
#[Medium]
final class MySqlViewPropertiesTest extends TestCase
{
    public function testDefaultsMatchAnUndecoratedView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertInstanceOf(MySqlViewProperties::class, $statement->properties);
        self::assertSame(ViewAlgorithm::Undefined, $statement->properties->algorithm);
        self::assertNull($statement->properties->definer);
        self::assertSame(ViewSecurity::Definer, $statement->properties->security);
    }

    public function testCarriesEveryDeclaredProperty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE ALGORITHM = TEMPTABLE DEFINER = 'app'@'localhost' SQL SECURITY INVOKER VIEW v AS SELECT 1");
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertInstanceOf(MySqlViewProperties::class, $statement->properties);
        self::assertSame(ViewAlgorithm::TempTable, $statement->properties->algorithm);
        self::assertInstanceOf(AccountName::class, $statement->properties->definer);
        self::assertSame('app', $statement->properties->definer->username);
        self::assertSame('localhost', $statement->properties->definer->host);
        self::assertSame(ViewSecurity::Invoker, $statement->properties->security);
    }

    public function testTheCurrentAccountCanDefineAView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DEFINER = CURRENT_USER VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertInstanceOf(MySqlViewProperties::class, $statement->properties);
        self::assertInstanceOf(CurrentAccount::class, $statement->properties->definer);
    }

}
