<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates which account operands each MySQL grammar release accepts; an unknown release defers to the binding snapshot.
 * @visibility SqlSemantics
 */
final class AccountForms
{
    /**
     * Account administration exists only in MySQL.
     * @throws InvalidStructure
     */
    public static function mysql(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Account administration requires MySQL.');
        }
    }

    /**
     * The bound grammar release, or null before a schema snapshot is attached.
     */
    public static function version(Origin $origin): ?string
    {
        return $origin->context?->schema()->grammarVersion;
    }

    /**
     * Whether the bound grammar is MySQL 5.6 or 5.7.
     */
    public static function legacy(Origin $origin): bool
    {
        return in_array(self::version($origin), ['mysql-5.6.51', 'mysql-5.7.44'], true);
    }

    /**
     * Requires a MySQL 8 or later grammar for the named feature.
     * @throws InvalidStructure
     */
    public static function modern(Origin $origin, string $feature): void
    {
        self::mysql($origin);
        if (self::legacy($origin)) {
            throw new InvalidStructure($feature . ' requires a MySQL 8 grammar.');
        }
    }

    /**
     * Requires a MySQL 5.6 or 5.7 grammar for the named feature once the release is known.
     * @throws InvalidStructure
     */
    public static function legacyOnly(Origin $origin, string $feature): void
    {
        self::mysql($origin);
        if (self::version($origin) !== null && !self::legacy($origin)) {
            throw new InvalidStructure($feature . ' requires a MySQL 5.6 or 5.7 grammar.');
        }
    }

    /**
     * Release-specific credential forms: pre-hashed passwords are legacy, generated ones are MySQL 8, plugin passwords need 5.7.
     * @throws InvalidStructure
     */
    public static function identification(Origin $origin, PasswordIdentification|HashIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification|null $identification): void
    {
        if ($identification instanceof HashIdentification) {
            self::legacyOnly($origin, 'IDENTIFIED BY PASSWORD');
        }
        if ($identification instanceof PluginPasswordIdentification && self::version($origin) === 'mysql-5.6.51') {
            throw new InvalidStructure('IDENTIFIED WITH plugin BY password requires MySQL 5.7 or later.');
        }
        if ($identification instanceof RandomPassword || $identification instanceof PluginRandomPasswordIdentification) {
            self::modern($origin, 'A generated password');
        }
    }

    /**
     * MySQL 5.6 lists accounts only; 5.7 changes first-factor credentials without replacement or retention.
     * @throws InvalidStructure
     */
    public static function alteration(Origin $origin, CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval $alteration): void
    {
        $version = self::version($origin);
        if ($version === 'mysql-5.6.51' && !$alteration instanceof AccountTarget) {
            throw new InvalidStructure('MySQL 5.6 alters accounts only through PASSWORD EXPIRE.');
        }
        if ($alteration instanceof AuthenticationChange) {
            self::identification($origin, $alteration->identification);
        }
        if ($version !== 'mysql-5.7.44') {
            return;
        }
        $credential = $alteration instanceof CredentialChange && $alteration->replacedPassword === null && !$alteration->retainCurrentPassword && !$alteration->identification instanceof RandomPassword;
        $encoded = $alteration instanceof AuthenticationChange && !$alteration->retainCurrentPassword && !$alteration->identification instanceof PluginRandomPasswordIdentification;
        if (!$credential && !$encoded && !$alteration instanceof PluginChange && !$alteration instanceof AccountTarget) {
            throw new InvalidStructure('This account alteration requires a MySQL 8 grammar.');
        }
    }

    /**
     * Validates the shared trailing clauses of CREATE USER and ALTER USER for the bound release.
     * @param list<ResourceLimit> $resourceLimits Ordered WITH limits
     * @param list<AccountPolicy|AccountLimit> $policies Ordered lock and password policies
     * @throws InvalidStructure
     */
    public static function clauses(Origin $origin, ConnectionSecurity|CertificateRequirements|null $requirement, array $resourceLimits, array $policies, ?AccountAnnotation $annotation): void
    {
        Collections::objects($resourceLimits, ResourceLimit::class);
        Collections::alternatives($policies, [AccountPolicy::class, AccountLimit::class]);
        if (self::version($origin) === 'mysql-5.6.51' && ($requirement !== null || $resourceLimits !== [])) {
            throw new InvalidStructure('MySQL 5.6 account definitions accept no REQUIRE or WITH clauses.');
        }
        if ($annotation !== null) {
            self::modern($origin, 'An account comment or attribute');
        }
        foreach ($policies as $policy) {
            self::policy($origin, $policy);
        }
    }

    /**
     * MySQL 5.7 knows only account locking and password expiry policies; 5.6 knows only expiry.
     * @throws InvalidStructure
     */
    public static function policy(Origin $origin, AccountPolicy|AccountLimit $policy): void
    {
        $version = self::version($origin);
        if ($version === 'mysql-5.6.51' && $policy !== AccountPolicy::ExpirePassword) {
            throw new InvalidStructure('MySQL 5.6 accepts only the PASSWORD EXPIRE policy.');
        }
        if ($version !== 'mysql-5.7.44') {
            return;
        }
        $known = $policy instanceof AccountLimit
            ? $policy->kind === AccountLimitKind::PasswordExpiryDays
            : in_array($policy, [AccountPolicy::Lock, AccountPolicy::Unlock, AccountPolicy::ExpirePassword, AccountPolicy::NeverExpirePassword, AccountPolicy::DefaultPasswordExpiry], true);
        if (!$known) {
            throw new InvalidStructure('This account policy requires a MySQL 8 grammar.');
        }
    }
}
