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
 * Activates granted session roles except a required exclusion list.
 * @visibility public
 * @example Binding the role operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET ROLE ALL EXCEPT 'writer'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Role\SetRolesExceptStatement // => true
 * @example Rejecting empty excludedRoles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET ROLE ALL EXCEPT 'r'");
 *     new \SqlSemantics\Model\Statement\Configuration\Role\SetRolesExceptStatement($statement->origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetRolesExceptStatement extends ConfigurationStatement
{
    /**
     * @param non-empty-list<AccountName> $excludedRoles
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $excludedRoles)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL role selection requires MySQL.');
        }
        Collections::objects($excludedRoles, AccountName::class);
        Collections::nonEmpty($excludedRoles);
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
        return new self($origin, $this->excludedRoles);
    }

    /**
     * Replaces excludedRoles in a new validated operation.
     * @param non-empty-list<AccountName> $excludedRoles
     */
    public function withExcludedRoles(array $excludedRoles): self
    {
        return $this->changed(new self($this->origin, $excludedRoles));
    }
}
