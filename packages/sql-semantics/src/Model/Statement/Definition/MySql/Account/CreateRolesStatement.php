<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;

/**
 * Declares MySQL 8 roles, keeping names and hosts separate.
 * @visibility public
 * @example Reading the declared roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE ROLE IF NOT EXISTS reader, 'writer'@'h'");
 *     [array_column($statement->roles, 'username'), $statement->ifNotExists] // => [['reader', 'writer'], true]
 * @example Rejecting an empty role list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\Account\CreateRolesStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName> $roles Ordered role names
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $roles, public readonly bool $ifNotExists = false)
    {
        AccountForms::modern($origin, 'Role creation');
        Collections::objects(Collections::nonEmpty($roles), AccountName::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the role request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->ifNotExists);
    }

    /**
     * Replaces the declared roles.
     * @param non-empty-list<AccountName> $roles Replacement role names
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->ifNotExists));
    }

    /**
     * Replaces the behavior when a role already exists.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->roles, $ifNotExists));
    }
}
