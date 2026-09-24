<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes every stored session default for a role selection, optionally within one database.
 * @visibility public
 * @example Reading the selection
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
 *     $statement->database // => 'shop'
 */
final class AlterRoleResetAllStatement extends BoundStatement
{
    /**
     * The request carries no parameter name because it addresses every stored default.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole|SessionRole|AllRoles $role,
        public readonly ?string $database,
    ) {
        RoleInvariant::database($origin, $database);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the reset while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->role, $this->database);
    }

    /**
     * Replaces the role selection.
     */
    public function withRole(NamedRole|SessionRole|AllRoles $role): self
    {
        return $this->changed(new self($this->origin, $role, $this->database));
    }

    /**
     * Replaces or removes the database qualifier.
     */
    public function withDatabase(?string $database): self
    {
        return $this->changed(new self($this->origin, $this->role, $database));
    }
}
