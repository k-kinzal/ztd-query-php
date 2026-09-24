<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates privilege lists, levels, grantees, and release-specific GRANT clauses.
 * @visibility SqlSemantics
 */
final class PrivilegeOperands
{
    /**
     * Column privileges need a table level, dynamic privileges the global level, and each static privilege one of its own levels.
     * @param non-empty-list<StaticPrivilege|ColumnPrivilege|DynamicPrivilege> $privileges Ordered privileges
     * @throws InvalidStructure
     */
    public static function privileges(Origin $origin, array $privileges, PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): void
    {
        Collections::alternatives(Collections::nonEmpty($privileges), [StaticPrivilege::class, ColumnPrivilege::class, DynamicPrivilege::class]);
        self::target($origin, $target);
        $level = self::level($target);
        foreach ($privileges as $privilege) {
            if ($privilege instanceof DynamicPrivilege) {
                if ($target !== PrivilegeScope::Global) {
                    throw new InvalidStructure('Dynamic privileges require the global privilege level.');
                }
                AccountForms::modern($origin, 'A dynamic privilege');
                continue;
            }
            if ($privilege instanceof ColumnPrivilege && !$target instanceof TableReference) {
                throw new InvalidStructure('Column privileges require a table privilege level.');
            }
            $static = $privilege instanceof ColumnPrivilege ? $privilege->privilege : $privilege;
            if (!in_array($level, $static->levels(), true)) {
                throw new InvalidStructure($static->value . ' is not defined at the ' . strtolower($level->value) . ' privilege level.');
            }
            if (in_array($static, [StaticPrivilege::CreateRole, StaticPrivilege::DropRole], true)) {
                AccountForms::modern($origin, 'A role privilege');
            }
        }
    }

    /**
     * The level a GRANT or REVOKE target addresses.
     */
    public static function level(PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): PrivilegeLevel
    {
        return match (true) {
            $target === PrivilegeScope::Global => PrivilegeLevel::Global,
            $target === PrivilegeScope::CurrentDatabase, $target instanceof DatabaseScope => PrivilegeLevel::Database,
            $target instanceof TableReference => PrivilegeLevel::Table,
            $target instanceof RoutineTarget => PrivilegeLevel::Routine,
        };
    }

    /**
     * A table level must be a MySQL table occurrence.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): void
    {
        AccountForms::mysql($origin);
        if ($target instanceof TableReference && $target->alias !== null) {
            throw new InvalidStructure('A table privilege level names the table without an alias.');
        }
    }

    /**
     * IF EXISTS and IGNORE UNKNOWN USER are MySQL 8 revocation policies.
     * @throws InvalidStructure
     */
    public static function revocation(Origin $origin, bool $ifExists, bool $ignoreUnknownUser): void
    {
        AccountForms::mysql($origin);
        if ($ifExists || $ignoreUnknownUser) {
            AccountForms::modern($origin, 'A REVOKE existence policy');
        }
    }

    /**
     * Legacy grantees may carry one credential; MySQL 8 grantees are plain account identities.
     * @param non-empty-list<AccountName|CurrentAccount|AccountDefinition> $grantees Ordered recipients
     * @throws InvalidStructure
     */
    public static function grantees(Origin $origin, array $grantees, bool $credentials): void
    {
        Collections::alternatives(Collections::nonEmpty($grantees), [AccountName::class, CurrentAccount::class, AccountDefinition::class]);
        foreach ($grantees as $grantee) {
            if (!$grantee instanceof AccountDefinition) {
                continue;
            }
            if (!$credentials || $grantee->identification === null || $grantee->additionalFactors !== []) {
                throw new InvalidStructure('A credentialed grantee requires exactly one identification and a granting operation.');
            }
            AccountForms::legacyOnly($origin, 'A credentialed grantee');
            AccountForms::identification($origin, $grantee->identification);
        }
    }

    /**
     * REQUIRE and resource limits belong to legacy GRANT; a grantor context belongs to MySQL 8.
     * @param list<ResourceLimit> $resourceLimits Ordered WITH limits
     * @throws InvalidStructure
     */
    public static function clauses(Origin $origin, ConnectionSecurity|CertificateRequirements|null $requirement, array $resourceLimits, ?Grantor $grantor): void
    {
        Collections::objects($resourceLimits, ResourceLimit::class);
        if ($requirement !== null || $resourceLimits !== []) {
            AccountForms::legacyOnly($origin, 'A GRANT requirement or resource limit');
        }
        if ($grantor !== null) {
            AccountForms::modern($origin, 'A grantor context');
        }
    }
}
