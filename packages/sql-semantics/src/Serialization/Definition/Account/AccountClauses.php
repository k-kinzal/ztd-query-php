<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirement;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes the REQUIRE, WITH, lock, password, and metadata clauses shared by account and legacy grant statements.
 * @visibility SqlSemantics
 */
final class AccountClauses
{
    /**
     * Certificate constraints are joined with AND so each attribute keeps its own keyword.
     * @return list<Tree>
     */
    public static function requirement(ConnectionSecurity|CertificateRequirements|null $requirement): array
    {
        if ($requirement === null) {
            return [];
        }
        if ($requirement instanceof ConnectionSecurity) {
            return [Build::keyword('REQUIRE ' . $requirement->value)];
        }
        $parts = [];
        foreach ($requirement->requirements as $constraint) {
            if ($parts !== []) {
                $parts[] = Build::keyword('AND');
            }
            $parts[] = self::constraint($constraint);
        }
        return [Build::keyword('REQUIRE'), new Tree('certificate-requirements', $parts)];
    }

    /**
     * One SUBJECT, ISSUER, or CIPHER constraint with its text literal.
     */
    public static function constraint(CertificateRequirement $constraint): Tree
    {
        return new Tree('certificate-requirement', [Build::keyword($constraint->attribute->value), Expressions::write($constraint->value)]);
    }

    /**
     * WITH followed by each limit keyword and count; an optional leading GRANT OPTION serves legacy GRANT.
     * @param list<ResourceLimit> $limits Ordered limits
     * @return list<Tree>
     */
    public static function limits(array $limits, bool $grantOption = false): array
    {
        if ($limits === [] && !$grantOption) {
            return [];
        }
        $parts = $grantOption ? [Build::keyword('GRANT OPTION')] : [];
        foreach ($limits as $limit) {
            $parts[] = new Tree('resource-limit', [Build::keyword($limit->kind->value), Accounts::count($limit->value)]);
        }
        return [Build::keyword('WITH'), new Tree('resource-limits', $parts)];
    }

    /**
     * Keyword policies are written as spelled; counted policies add their number and DAY unit where required.
     * @param list<AccountPolicy|AccountLimit> $policies Ordered policies
     * @return list<Tree>
     */
    public static function policies(array $policies): array
    {
        return array_map(static fn (AccountPolicy|AccountLimit $policy): Tree => $policy instanceof AccountPolicy
            ? Build::keyword($policy->value)
            : new Tree('account-limit', [Build::keyword($policy->kind->value), Accounts::count($policy->value), ...(in_array($policy->kind, [AccountLimitKind::PasswordExpiryDays, AccountLimitKind::PasswordReuseDays], true) ? [Build::keyword('DAY')] : [])]), $policies);
    }

    /**
     * COMMENT or ATTRIBUTE followed by its literal.
     * @return list<Tree>
     */
    public static function annotation(?AccountAnnotation $annotation): array
    {
        return $annotation === null ? [] : [Build::keyword($annotation->form->value), Expressions::write($annotation->text)];
    }
}
