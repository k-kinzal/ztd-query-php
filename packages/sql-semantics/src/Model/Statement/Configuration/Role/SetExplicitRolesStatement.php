<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Role;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Activates a required list of named roles in the current session.
 * @visibility public
 * @example Binding the role operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET ROLE 'reader', 'writer'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Role\SetExplicitRolesStatement // => true
 * @example Rejecting empty roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET ROLE 'r'");
 *     new \SqlSemantics\Model\Statement\Configuration\Role\SetExplicitRolesStatement($statement->origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetExplicitRolesStatement extends ConfigurationStatement
{
    /**
     * @param non-empty-list<AccountName> $roles
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $roles)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL role selection requires MySQL.');
        }
        Collections::objects($roles, AccountName::class);
        Collections::nonEmpty($roles);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the role request while changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles);
    }

    /**
     * Replaces roles in a new validated operation.
     * @param non-empty-list<AccountName> $roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles));
    }
}
