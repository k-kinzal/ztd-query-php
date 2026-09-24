<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\GeneratedPasswordRows;
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
 * Changes account authentication per listed account and applies shared requirements, limits, and policies to all of them.
 * @visibility public
 * @example Reading per-account changes and a shared policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER IF EXISTS a IDENTIFIED BY 'x', b PASSWORD EXPIRE NEVER");
 *     [$statement->ifExists, $statement->alterations[0]::class, $statement->policies[0]->value] // => [true, \SqlSemantics\Model\Definition\Account\Alteration\CredentialChange::class, 'PASSWORD EXPIRE NEVER']
 * @example Reading the MySQL 5.6 per-account expiry form
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE');
 *     [count($statement->alterations), $statement->policies] // => [2, [\SqlSemantics\Model\Definition\Account\Policy\AccountPolicy::ExpirePassword]]
 * @example Rejecting shared clauses for the connecting client account
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER USER() DISCARD OLD PASSWORD');
 *     $statement->withPolicies([\SqlSemantics\Model\Definition\Account\Policy\AccountPolicy::Lock]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterUsersStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval> $alterations Ordered per-account changes
     * @param list<ResourceLimit> $resourceLimits Ordered WITH limits
     * @param list<AccountPolicy|AccountLimit> $policies Ordered lock and password policies
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $alterations,
        public readonly bool $ifExists = false,
        public readonly ConnectionSecurity|CertificateRequirements|null $requirement = null,
        public readonly array $resourceLimits = [],
        public readonly array $policies = [],
        public readonly ?AccountAnnotation $annotation = null,
    ) {
        AccountForms::mysql($origin);
        Collections::alternatives(Collections::nonEmpty($alterations), [CredentialChange::class, AuthenticationChange::class, PluginChange::class, AccountTarget::class, OldPasswordDiscard::class, FactorChange::class, FactorRemoval::class]);
        foreach ($alterations as $alteration) {
            AccountForms::alteration($origin, $alteration);
            if ($alteration->account instanceof ClientAccount && (count($alterations) > 1 || $requirement !== null || $resourceLimits !== [] || $policies !== [] || $annotation !== null)) {
                throw new InvalidStructure('The connecting client account is altered alone, without shared clauses.');
            }
        }
        if (AccountForms::version($origin) === 'mysql-5.6.51' && ($ifExists || $policies !== [AccountPolicy::ExpirePassword])) {
            throw new InvalidStructure('MySQL 5.6 ALTER USER expires the listed passwords and accepts no other clause.');
        }
        AccountForms::clauses($origin, $requirement, $resourceLimits, $policies, $annotation);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * The generated-password fields when any alteration requests a random password; otherwise no rows are produced.
     * @return list<\SqlSemantics\Model\OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        foreach ($this->alterations as $alteration) {
            if (GeneratedPasswordRows::alteration($alteration)) {
                return GeneratedPasswordRows::columns($this->source, $this->scopeId, $alteration->account);
            }
        }
        return [];
    }

    /**
     * Retains the complete request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->alterations, $this->ifExists, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation);
    }

    /**
     * Replaces the per-account changes without changing the shared clauses.
     * @param non-empty-list<CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval> $alterations Replacement changes
     */
    public function withAlterations(array $alterations): self
    {
        return $this->changed(new self($this->origin, $alterations, $this->ifExists, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the behavior when an account does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->alterations, $ifExists, $this->requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the connection requirement.
     */
    public function withRequirement(ConnectionSecurity|CertificateRequirements|null $requirement): self
    {
        return $this->changed(new self($this->origin, $this->alterations, $this->ifExists, $requirement, $this->resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the resource limits.
     * @param list<ResourceLimit> $resourceLimits Replacement limits
     */
    public function withResourceLimits(array $resourceLimits): self
    {
        return $this->changed(new self($this->origin, $this->alterations, $this->ifExists, $this->requirement, $resourceLimits, $this->policies, $this->annotation));
    }

    /**
     * Replaces the lock and password policies.
     * @param list<AccountPolicy|AccountLimit> $policies Replacement policies
     */
    public function withPolicies(array $policies): self
    {
        return $this->changed(new self($this->origin, $this->alterations, $this->ifExists, $this->requirement, $this->resourceLimits, $policies, $this->annotation));
    }

    /**
     * Replaces the account comment or attribute.
     */
    public function withAnnotation(?AccountAnnotation $annotation): self
    {
        return $this->changed(new self($this->origin, $this->alterations, $this->ifExists, $this->requirement, $this->resourceLimits, $this->policies, $annotation));
    }
}
