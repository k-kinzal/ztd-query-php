<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AccountForms::class)]
#[Medium]
final class AccountFormsTest extends TestCase
{
    public function testMysqlRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::mysql($origin);
    }

    public function testVersionReadsTheBoundGrammarRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        self::assertSame('mysql-5.7.44', AccountForms::version($origin));
    }

    #[TestWith(['mysql-5.6.51', true])]
    #[TestWith(['mysql-5.7.44', true])]
    #[TestWith(['mysql-8.0.44', false])]
    #[TestWith(['mysql-9.1.0', false])]
    public function testLegacyRecognizesTheMySql5Releases(string $version, bool $legacy): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        self::assertSame($legacy, AccountForms::legacy($origin));
    }

    public function testModernRejectsAMySql57Grammar(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::modern($origin, 'Role creation');
    }

    public function testLegacyOnlyRejectsAMySql8Grammar(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::legacyOnly($origin, 'IDENTIFIED BY PASSWORD');
    }

    public function testIdentificationRejectsAGeneratedPasswordOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::identification($origin, RandomPassword::Generated);
    }

    public function testIdentificationAcceptsAPluginOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        AccountForms::identification($origin, new PluginIdentification('p'));
        self::assertSame('mysql-5.6.51', AccountForms::version($origin));
    }

    public function testAlterationRejectsAPluginChangeOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::alteration($origin, new PluginChange(new AccountName('u'), 'p'));
    }

    public function testAlterationRejectsADiscardOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::alteration($origin, new OldPasswordDiscard(new AccountName('u')));
    }

    public function testAlterationAcceptsAPlainTargetOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        AccountForms::alteration($origin, new AccountTarget(new AccountName('u')));
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testClausesRejectARequirementOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::clauses($origin, ConnectionSecurity::Ssl, [], [], null);
    }

    public function testClausesAcceptLimitsOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        AccountForms::clauses($origin, ConnectionSecurity::X509, [new ResourceLimit(ResourceLimitKind::UserConnections, 3)], [AccountPolicy::Lock], null);
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testPolicyRejectsPasswordHistoryOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::policy($origin, new AccountLimit(AccountLimitKind::PasswordHistory, 2));
    }

    public function testPolicyRejectsAccountLockingOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AccountForms::policy($origin, AccountPolicy::Lock);
    }
}
