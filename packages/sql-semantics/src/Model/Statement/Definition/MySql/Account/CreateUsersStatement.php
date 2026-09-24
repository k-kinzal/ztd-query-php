<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\GeneratedPasswordRows;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares MySQL accounts with their credentials, default roles, connection requirements, limits, and policies.
 * @visibility public
 * @example Reading the declared accounts and shared policies
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER IF NOT EXISTS 'a'@'h' IDENTIFIED BY 'x', b DEFAULT ROLE reader ACCOUNT LOCK");
 *     [count($statement->accounts), $statement->ifNotExists, $statement->defaultRoles[0]->username, $statement->policies[0]->value] // => [2, true, 'reader', 'ACCOUNT LOCK']
 * @example Reading the generated-password result of a random password request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER a IDENTIFIED BY RANDOM PASSWORD');
 *     array_column($statement->resultColumns(), 'name') // => ['user', 'host', 'generated password', 'auth_factor']
 * @example Rejecting a definition without accounts
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateUsersStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<AccountDefinition|InitialAuthenticationDefinition> $accounts Ordered account definitions
     * @param list<AccountName> $defaultRoles Roles activated by default; MySQL 8 only
     * @param list<ResourceLimit> $resourceLimits Ordered WITH limits
     * @param list<AccountPolicy|AccountLimit> $policies Ordered lock and password policies
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $accounts,
        public readonly bool $ifNotExists = false,
        public readonly array $defaultRoles = [],
        public readonly ConnectionSecurity|CertificateRequirements|null $requirement = null,
        public readonly array $resourceLimits = [],
        public readonly array $policies = [],
        public readonly ?AccountAnnotation $annotation = null,
    ) {
        AccountForms::mysql($origin);
        Collections::alternatives(Collections::nonEmpty($accounts), [AccountDefinition::class, InitialAuthenticationDefinition::class]);
        Collections::objects($defaultRoles, AccountName::class);
        if (AccountForms::version($origin) === 'mysql-5.6.51' && ($ifNotExists || $policies !== [])) {
            throw new InvalidStructure('MySQL 5.6 account creation has no IF NOT EXISTS or account policies.');
        }
        if ($defaultRoles !== []) {
            AccountForms::modern($origin, 'A default role clause');
        }
        foreach ($accounts as $account) {
            if ($account instanceof InitialAuthenticationDefinition) {
                AccountForms::modern($origin, 'Initial authentication');
                continue;
            }
            AccountForms::identification($origin, $account->identification);
            if ($account->additionalFactors !== []) {
                AccountForms::modern($origin, 'Multifactor authentication');
            }
        }
        AccountForms::clauses($origin, $requirement, $resourceLimits, $policies, $annotation);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * The generated-password fields when any account requests a random password; otherwise no rows are produced.
     * @return list<\SqlSemantics\Model\OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        foreach ($this->accounts as $account) {
            if (GeneratedPasswordRows::definition($account)) {
                return GeneratedPasswordRows::columns($this->source, $this->scopeId, $account->account);
            }
        }
        return [];
    }

    /**
     * Retains the complete definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->accounts, $this->ifNotExists, $this->defaultRoles, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation);
    }

    /**
     * Replaces the account definitions without changing the shared clauses.
     * @param non-empty-list<AccountDefinition|InitialAuthenticationDefinition> $accounts Replacement definitions
     */
    public function withAccounts(array $accounts): self
    {
        return $this->changed(new self($this->origin, $accounts, $this->ifNotExists, $this->defaultRoles, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the behavior when an account already exists.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $ifNotExists, $this->defaultRoles, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the roles activated by default.
     * @param list<AccountName> $defaultRoles Replacement default roles
     */
    public function withDefaultRoles(array $defaultRoles): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $this->ifNotExists, $defaultRoles, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the connection requirement.
     */
    public function withRequirement(ConnectionSecurity|CertificateRequirements|null $requirement): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $this->ifNotExists, $this->defaultRoles, $requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the resource limits.
     * @param list<ResourceLimit> $resourceLimits Replacement limits
     */
    public function withResourceLimits(array $resourceLimits): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $this->ifNotExists, $this->defaultRoles, $this->requirement, $resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the lock and password policies.
     * @param list<AccountPolicy|AccountLimit> $policies Replacement policies
     */
    public function withPolicies(array $policies): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $this->ifNotExists, $this->defaultRoles, $this->requirement, $this->resourceLimits, $policies, $this->annotation));
    }

    /**
     * Replaces the account comment or attribute.
     */
    public function withAnnotation(?AccountAnnotation $annotation): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $this->ifNotExists, $this->defaultRoles, $this->requirement, $this->resourceLimits, $this->policies, $annotation));
    }
}
