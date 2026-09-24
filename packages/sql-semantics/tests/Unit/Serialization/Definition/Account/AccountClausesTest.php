<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Definition\Account\Policy\CertificateAttribute;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirement;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Definition\Account\AccountClauses;

#[CoversClass(AccountClauses::class)]
#[Medium]
final class AccountClausesTest extends TestCase
{
    public function testRequirementJoinsCertificateConstraintsWithAnd(): void
    {
        $value = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'x'", 0));
        self::assertInstanceOf(Literal::class, $value);
        $requirement = new CertificateRequirements([new CertificateRequirement(CertificateAttribute::Subject, $value), new CertificateRequirement(CertificateAttribute::Cipher, $value)]);
        self::assertSame("REQUIRE SUBJECT 'x' AND CIPHER 'x'", (new Tree('clause', AccountClauses::requirement($requirement)))->toString());
        self::assertSame('REQUIRE SSL', (new Tree('clause', AccountClauses::requirement(ConnectionSecurity::Ssl)))->toString());
        self::assertSame([], AccountClauses::requirement(null));
    }

    public function testConstraintWritesTheAttributeAndLiteral(): void
    {
        $value = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'/CN=a'", 0));
        self::assertInstanceOf(Literal::class, $value);
        self::assertSame("ISSUER '/CN=a'", AccountClauses::constraint(new CertificateRequirement(CertificateAttribute::Issuer, $value))->toString());
    }

    public function testLimitsWriteTheLegacyGrantOptionFirst(): void
    {
        self::assertSame('WITH GRANT OPTION MAX_QUERIES_PER_HOUR 5', (new Tree('clause', AccountClauses::limits([new ResourceLimit(ResourceLimitKind::QueriesPerHour, 5)], true)))->toString());
        self::assertSame('WITH GRANT OPTION', (new Tree('clause', AccountClauses::limits([], true)))->toString());
        self::assertSame([], AccountClauses::limits([]));
    }

    public function testPoliciesAddDayUnitsOnlyToIntervals(): void
    {
        $policies = AccountClauses::policies([new AccountLimit(AccountLimitKind::PasswordExpiryDays, 9), new AccountLimit(AccountLimitKind::PasswordHistory, 2), AccountPolicy::NeverExpirePassword]);
        self::assertSame('PASSWORD EXPIRE INTERVAL 9 DAY PASSWORD HISTORY 2 PASSWORD EXPIRE NEVER', (new Tree('clause', $policies))->toString());
    }

    public function testAnnotationWritesTheFormAndLiteral(): void
    {
        $text = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'note'", 0));
        self::assertInstanceOf(Literal::class, $text);
        self::assertSame("COMMENT 'note'", (new Tree('clause', AccountClauses::annotation(new AccountAnnotation(AnnotationForm::Comment, $text))))->toString());
        self::assertSame([], AccountClauses::annotation(null));
    }
}
