<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Account\AccountClauses;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Definition\Account\Policy\CertificateAttribute;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AccountClauses::class)]
#[Medium]
final class AccountClausesTest extends TestCase
{
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testRequirementReadsCertificateConstraintsInOrder(string $version): void
    {
        $tree = (new DialectParser(Dialect::MySql, $version))->parse("CREATE USER u REQUIRE ISSUER 'i' AND SUBJECT 's' CIPHER 'c'");
        $requirement = AccountClauses::requirement($tree->find('create')[0]);
        self::assertInstanceOf(CertificateRequirements::class, $requirement);
        self::assertSame([CertificateAttribute::Issuer, CertificateAttribute::Subject, CertificateAttribute::Cipher], array_column($requirement->requirements, 'attribute'));
    }

    public function testRequirementReadsATransportRequirement(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER USER u REQUIRE X509');
        self::assertSame(ConnectionSecurity::X509, AccountClauses::requirement($tree->find('alter_user_stmt')[0]));
        self::assertNull(AccountClauses::requirement($tree->find('alter_user_list')[0]));
    }

    public function testRequirementRejectsARepeatedAttribute(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CertificateRequirement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE USER u REQUIRE CIPHER 'a' AND CIPHER 'b'");
    }

    #[TestWith(['mysql-5.6.51', 'GRANT SELECT ON *.* TO u WITH MAX_QUERIES_PER_HOUR 1 GRANT OPTION MAX_USER_CONNECTIONS 2'])]
    #[TestWith(['mysql-5.7.44', 'CREATE USER u WITH MAX_QUERIES_PER_HOUR 1 MAX_USER_CONNECTIONS 2'])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER u WITH MAX_QUERIES_PER_HOUR 1 MAX_USER_CONNECTIONS 2'])]
    public function testLimitsReadEveryResourceLimitInOrder(string $version, string $sql): void
    {
        $tree = (new DialectParser(Dialect::MySql, $version))->parse($sql);
        self::assertEquals([new ResourceLimit(ResourceLimitKind::QueriesPerHour, 1), new ResourceLimit(ResourceLimitKind::UserConnections, 2)], AccountClauses::limits($tree));
    }

    public function testLimitsRejectAFractionalCount(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AccountLimit->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER u WITH MAX_UPDATES_PER_HOUR 2.5');
    }

    public function testPoliciesReadKeywordAndCountedPolicies(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('CREATE USER u PASSWORD EXPIRE INTERVAL 90 DAY ACCOUNT LOCK PASSWORD HISTORY DEFAULT FAILED_LOGIN_ATTEMPTS 4 PASSWORD_LOCK_TIME UNBOUNDED');
        self::assertEquals([
            new AccountLimit(AccountLimitKind::PasswordExpiryDays, 90),
            AccountPolicy::Lock,
            AccountPolicy::DefaultPasswordHistory,
            new AccountLimit(AccountLimitKind::FailedLoginAttempts, 4),
            AccountPolicy::UnboundedPasswordLock,
        ], AccountClauses::policies($tree));
    }

    public function testPoliciesRejectAnIntervalOutOfRange(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AccountLimit->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER u PASSWORD EXPIRE INTERVAL 70000 DAY');
    }

    public function testAnnotationKeepsTheMetadataLiteral(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("ALTER USER u ATTRIBUTE '{\"a\": 1}'");
        $annotation = AccountClauses::annotation($tree->find('alter_user_stmt')[0]);
        self::assertNotNull($annotation);
        self::assertSame(AnnotationForm::Attribute, $annotation->form);
        self::assertSame("'{\"a\": 1}'", $annotation->text->text);
    }

    public function testGrantOptionFindsTheKeywordsInLegacyOptions(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('GRANT SELECT ON *.* TO u WITH MAX_QUERIES_PER_HOUR 1 GRANT OPTION');
        $body = $tree->find('grant_command')[0];
        self::assertTrue(AccountClauses::grantOption($body, 'grant_options'));
        self::assertFalse(AccountClauses::grantOption($body, 'opt_grant_option'));
    }

    #[TestWith(['create user u require ssl', 'CREATE USER \'u\' REQUIRE SSL'])]
    #[TestWith(['create user u require cipher \'a\' and issuer \'b\'', 'CREATE USER \'u\' REQUIRE CIPHER \'a\' AND ISSUER \'b\''])]
    #[TestWith(['alter user u with max_queries_per_hour 1', 'ALTER USER \'u\' WITH MAX_QUERIES_PER_HOUR 1'])]
    #[TestWith(['create user u password expire interval 90 day account lock failed_login_attempts 3', 'CREATE USER \'u\' PASSWORD EXPIRE INTERVAL 90 DAY ACCOUNT LOCK FAILED_LOGIN_ATTEMPTS 3'])]
    #[TestWith(['alter user u comment \'x\'', 'ALTER USER \'u\' COMMENT \'x\''])]
    public function testClausesReadLowercaseKeywords(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql)));
    }

    public function testAnnotationIsNullWithoutTheClause(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER USER u ACCOUNT LOCK');
        self::assertNull(AccountClauses::annotation($tree->find('alter_user_stmt')[0]));
    }

    public function testGrantOptionReadsLowercaseKeywordsAndIgnoresOtherOptions(): void
    {
        $granted = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('grant select on *.* to u with grant option')->find('grant_command')[0];
        $limited = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('GRANT SELECT ON *.* TO u WITH MAX_QUERIES_PER_HOUR 1')->find('grant_command')[0];
        self::assertSame([true, false], [AccountClauses::grantOption($granted, 'grant_options'), AccountClauses::grantOption($limited, 'grant_options')]);
    }
}
