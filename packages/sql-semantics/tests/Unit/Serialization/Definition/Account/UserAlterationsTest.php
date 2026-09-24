<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorIdentification;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterUsersStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Account\UserAlterations;

#[CoversClass(UserAlterations::class)]
#[Medium]
final class UserAlterationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE', "ALTER USER 'a' PASSWORD EXPIRE, 'b' PASSWORD EXPIRE"])]
    #[TestWith(['mysql-5.7.44', 'ALTER USER IF EXISTS a REQUIRE SSL PASSWORD EXPIRE DEFAULT', "ALTER USER IF EXISTS 'a' REQUIRE SSL PASSWORD EXPIRE DEFAULT"])]
    #[TestWith(['mysql-8.4.7', "ALTER USER a IDENTIFIED BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD WITH MAX_USER_CONNECTIONS 1 COMMENT 'c'", "ALTER USER 'a' IDENTIFIED BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD WITH MAX_USER_CONNECTIONS 1 COMMENT 'c'"])]
    public function testWriteSpellsEachReleaseForm(string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame($expected, UserAlterations::write($statement)->toString());
    }

    public function testAlterationWritesExactlyTheClausesOfEachChange(): void
    {
        self::assertSame("'a' IDENTIFIED WITH `p` BY RANDOM PASSWORD RETAIN CURRENT PASSWORD", UserAlterations::alteration(new AuthenticationChange(new AccountName('a'), new PluginRandomPasswordIdentification('p'), true))->toString());
        self::assertSame("'a' IDENTIFIED WITH `p`", UserAlterations::alteration(new PluginChange(new AccountName('a'), 'p'))->toString());
        self::assertSame('USER() DISCARD OLD PASSWORD', UserAlterations::alteration(new OldPasswordDiscard(ClientAccount::Connected))->toString());
        self::assertSame("'a' DROP 3 FACTOR DROP 2 FACTOR", UserAlterations::alteration(new FactorRemoval(new AccountName('a'), [AuthenticationFactor::Third, AuthenticationFactor::Second]))->toString());
    }

    public function testFactorWritesTheOperationFactorAndIdentification(): void
    {
        self::assertSame('MODIFY 2 FACTOR IDENTIFIED WITH `p`', UserAlterations::factor('MODIFY', new FactorIdentification(AuthenticationFactor::Second, new PluginIdentification('p')))->toString());
    }
}
