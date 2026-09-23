<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes removal of MySQL roles, keeping names and hosts separate.
 * @visibility public
 * @example Inspecting account identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("DROP ROLE 'reader'@'localhost'");
 *     $statement->roles[0]->host // => 'localhost'
 * @example Rejecting a missing target list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\DropRolesStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 * @example Rejecting the authenticated-account symbol as a named role
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\DropRolesStatement($origin, [\SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName> $roles Ordered removal targets
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $roles, public readonly bool $ifExists = false)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This account removal form requires MySQL.');
        }
        if (in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('Role removal requires a MySQL release with roles.');
        }
        Collections::alternatives(Collections::nonEmpty($roles), [AccountName::class]);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the complete removal request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->ifExists);
    }

    /**
     * Replaces the targets without changing the original request.
     * @param non-empty-list<AccountName> $roles Ordered replacement targets
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->ifExists));
    }

    /**
     * Replaces the behavior when a target does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->roles, $ifExists));
    }
}
