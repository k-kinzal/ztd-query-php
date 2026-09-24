<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Statement\Definition\MySql\Account\UnregisterFactorStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UnregisterFactorStatement::class)]
#[Medium]
final class UnregisterFactorStatementTest extends TestCase
{
    public function testWithAccountReplacesTheAccountWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a 2 FACTOR UNREGISTER');
        self::assertInstanceOf(UnregisterFactorStatement::class, $statement);
        $changed = $statement->withAccount(ClientAccount::Connected);
        self::assertEquals(new AccountName('a'), $statement->account);
        self::assertSame('ALTER USER USER() 2 FACTOR UNREGISTER', $changed->toString());
    }

    public function testWithFactorReplacesTheFactor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a 2 FACTOR UNREGISTER');
        self::assertInstanceOf(UnregisterFactorStatement::class, $statement);
        $changed = $statement->withFactor(AuthenticationFactor::Third);
        self::assertSame(AuthenticationFactor::Second, $statement->factor);
        self::assertSame("ALTER USER 'a' 3 FACTOR UNREGISTER", $changed->toString());
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a 3 FACTOR UNREGISTER');
        self::assertInstanceOf(UnregisterFactorStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame(AuthenticationFactor::Third, $copy->factor);
    }

    public function testWithOriginRejectsAMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a 2 FACTOR UNREGISTER');
        self::assertInstanceOf(UnregisterFactorStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
