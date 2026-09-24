<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * REVOKE ALL [PRIVILEGES] at one level from accounts; the level keeps its GRANT OPTION.
 * @visibility public
 * @example Reading the level of an all-privileges revocation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('REVOKE ALL ON FUNCTION app.f FROM u');
 *     [$statement->target->kind->value, $statement->target->name->parts] // => ['FUNCTION', ['app', 'f']]
 */
final class RevokeAllPrivilegesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Ordered accounts losing the privileges
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target,
        public readonly array $grantees,
        public readonly bool $ifExists = false,
        public readonly bool $ignoreUnknownUser = false,
    ) {
        PrivilegeOperands::target($origin, $target);
        PrivilegeOperands::grantees($origin, $grantees, false);
        PrivilegeOperands::revocation($origin, $ifExists, $ignoreUnknownUser);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Revoke;
    }

    /**
     * Retains the complete revocation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->target, $this->grantees, $this->ifExists, $this->ignoreUnknownUser);
    }

    /**
     * Replaces the privilege level.
     */
    public function withTarget(PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the accounts losing the privileges.
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Replacement accounts
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->target, $grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when a privilege was not granted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->grantees, $ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when an account does not exist.
     */
    public function withIgnoreUnknownUser(bool $ignoreUnknownUser): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->grantees, $this->ifExists, $ignoreUnknownUser));
    }
}
