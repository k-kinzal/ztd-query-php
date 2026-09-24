<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Scalar\Value\Literal;
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

    public function testModernRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Account administration requires MySQL.');
        AccountForms::modern($origin, 'Role creation');
    }

    public function testModernNamesTheFeature(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Role creation requires a MySQL 8 grammar.');
        AccountForms::modern($origin, 'Role creation');
    }

    public function testLegacyOnlyNamesTheFeature(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-9.1.0'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('IDENTIFIED BY PASSWORD requires a MySQL 5.6 or 5.7 grammar.');
        AccountForms::legacyOnly($origin, 'IDENTIFIED BY PASSWORD');
    }

    public function testLegacyOnlyAcceptsAMySql57Grammar(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        AccountForms::legacyOnly($origin, 'IDENTIFIED BY PASSWORD');
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testIdentificationRejectsAPreHashedPasswordOnMySql8(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('IDENTIFIED BY PASSWORD requires a MySQL 5.6 or 5.7 grammar.');
        AccountForms::identification($origin, new HashIdentification($secret));
    }

    public function testIdentificationRejectsAPluginPasswordOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('IDENTIFIED WITH plugin BY password requires MySQL 5.7 or later.');
        AccountForms::identification($origin, new PluginPasswordIdentification('p', $secret));
    }

    public function testIdentificationAcceptsAPluginPasswordOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        AccountForms::identification($origin, new PluginPasswordIdentification('p', $secret));
        self::assertSame('mysql-5.7.44', AccountForms::version($origin));
    }

    public function testAlterationRejectsAPreHashedAuthenticationOnMySql8(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        AccountForms::alteration($origin, new AuthenticationChange(new AccountName('u'), new HashIdentification($secret)));
    }

    public function testAlterationAcceptsADiscardOnMySql8(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        AccountForms::alteration($origin, new OldPasswordDiscard(new AccountName('u')));
        self::assertFalse(AccountForms::legacy($origin));
    }

    public function testAlterationAcceptsAPlainCredentialChangeOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        AccountForms::alteration($origin, new CredentialChange(new AccountName('u'), new PasswordIdentification($secret)));
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testAlterationRejectsAReplacedPasswordOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This account alteration requires a MySQL 8 grammar.');
        AccountForms::alteration($origin, new CredentialChange(new AccountName('u'), new PasswordIdentification($secret), $secret));
    }

    public function testAlterationRejectsARetainedPasswordOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This account alteration requires a MySQL 8 grammar.');
        AccountForms::alteration($origin, new CredentialChange(new AccountName('u'), new PasswordIdentification($secret), null, true));
    }

    public function testAlterationRejectsAGeneratedCredentialOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This account alteration requires a MySQL 8 grammar.');
        AccountForms::alteration($origin, new CredentialChange(new AccountName('u'), RandomPassword::Generated));
    }

    public function testAlterationAcceptsAPreHashedAuthenticationOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        AccountForms::alteration($origin, new AuthenticationChange(new AccountName('u'), new HashIdentification($secret)));
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testAlterationRejectsARetainedAuthenticationOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This account alteration requires a MySQL 8 grammar.');
        AccountForms::alteration($origin, new AuthenticationChange(new AccountName('u'), new HashIdentification($secret), true));
    }

    public function testAlterationAcceptsAPluginChangeOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        AccountForms::alteration($origin, new PluginChange(new AccountName('u'), 'p'));
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testAlterationAcceptsAPlainTargetOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        AccountForms::alteration($origin, new AccountTarget(new AccountName('u')));
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testClausesRejectResourceLimitsOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('MySQL 5.6 account definitions accept no REQUIRE or WITH clauses.');
        AccountForms::clauses($origin, null, [new ResourceLimit(ResourceLimitKind::UserConnections, 3)], [], null);
    }

    public function testClausesAcceptPlainDefinitionsOnMySql56(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        AccountForms::clauses($origin, null, [], [AccountPolicy::ExpirePassword], null);
        self::assertTrue(AccountForms::legacy($origin));
    }

    public function testClausesRejectAnAnnotationOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('An account comment or attribute requires a MySQL 8 grammar.');
        AccountForms::clauses($origin, null, [], [], new AccountAnnotation(AnnotationForm::Comment, $secret));
    }

    public function testClausesCheckEveryPolicy(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('MySQL 5.6 accepts only the PASSWORD EXPIRE policy.');
        AccountForms::clauses($origin, null, [], [AccountPolicy::ExpirePassword, AccountPolicy::Lock], null);
    }

    public function testPolicyAcceptsPasswordHistoryOnMySql8(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        AccountForms::policy($origin, new AccountLimit(AccountLimitKind::PasswordHistory, 2));
        self::assertFalse(AccountForms::legacy($origin));
    }

    public function testPolicyAcceptsExpiryDaysOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        AccountForms::policy($origin, new AccountLimit(AccountLimitKind::PasswordExpiryDays, 2));
        self::assertTrue(AccountForms::legacy($origin));
    }
}
