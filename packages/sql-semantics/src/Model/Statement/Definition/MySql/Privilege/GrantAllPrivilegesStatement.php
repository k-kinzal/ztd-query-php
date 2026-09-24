<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * GRANT ALL [PRIVILEGES] at one level: every privilege the level supports, without a privilege list.
 * @visibility public
 * @example Reading the level of an all-privileges grant
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT ALL PRIVILEGES ON app.* TO u, CURRENT_USER()');
 *     [$statement->target->database, $statement->grantees[1]] // => ['app', \SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated]
 * @example Rejecting an empty recipient list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT ALL ON *.* TO u');
 *     $statement->withGrantees([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class GrantAllPrivilegesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName|CurrentAccount|AccountDefinition> $grantees Ordered recipients; legacy releases may attach one credential each
     * @param list<ResourceLimit> $resourceLimits Legacy WITH limits
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target,
        public readonly array $grantees,
        public readonly bool $withGrantOption = false,
        public readonly ConnectionSecurity|CertificateRequirements|null $requirement = null,
        public readonly array $resourceLimits = [],
        public readonly ?Grantor $grantor = null,
    ) {
        PrivilegeOperands::target($origin, $target);
        PrivilegeOperands::grantees($origin, $grantees, true);
        PrivilegeOperands::clauses($origin, $requirement, $resourceLimits, $grantor);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Grant;
    }

    /**
     * Retains the complete grant while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->target, $this->grantees, $this->withGrantOption, $this->requirement, $this->resourceLimits, $this->grantor);
    }

    /**
     * Replaces the privilege level.
     */
    public function withTarget(PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->grantees, $this->withGrantOption, $this->requirement, $this->resourceLimits, $this->grantor));
    }

    /**
     * Replaces the recipients.
     * @param non-empty-list<AccountName|CurrentAccount|AccountDefinition> $grantees Replacement recipients
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->target, $grantees, $this->withGrantOption, $this->requirement, $this->resourceLimits, $this->grantor));
    }

    /**
     * Replaces whether recipients may grant the privileges onward.
     */
    public function withWithGrantOption(bool $withGrantOption): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->grantees, $withGrantOption, $this->requirement, $this->resourceLimits, $this->grantor));
    }

    /**
     * Replaces the legacy connection requirement.
     */
    public function withRequirement(ConnectionSecurity|CertificateRequirements|null $requirement): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->grantees, $this->withGrantOption, $requirement, $this->resourceLimits, $this->grantor));
    }

    /**
     * Replaces the legacy resource limits.
     * @param list<ResourceLimit> $resourceLimits Replacement limits
     */
    public function withResourceLimits(array $resourceLimits): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->grantees, $this->withGrantOption, $this->requirement, $resourceLimits, $this->grantor));
    }

    /**
     * Replaces the grantor context.
     */
    public function withGrantor(?Grantor $grantor): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->grantees, $this->withGrantOption, $this->requirement, $this->resourceLimits, $grantor));
    }
}
