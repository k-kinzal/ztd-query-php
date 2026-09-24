<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one stored session default for a role selection, optionally within one database.
 * @visibility public
 * @example Reading the removed default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
 *     $statement->role->name // => 'app'
 *     $statement->setting->name // => ['timezone']
 */
final class AlterRoleResetStatement extends BoundStatement
{
    /**
     * The reset addresses the session scope without an existence qualifier.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole|SessionRole|AllRoles $role,
        public readonly ?string $database,
        public readonly ResetSetting $setting,
    ) {
        RoleInvariant::database($origin, $database);
        if ($setting->scope !== SettingScope::Session || $setting->ifExists) {
            throw new InvalidStructure('A stored role setting reset uses the session scope without IF EXISTS.');
        }
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
        return new self($origin, $this->role, $this->database, $this->setting);
    }

    /**
     * Replaces the role selection.
     */
    public function withRole(NamedRole|SessionRole|AllRoles $role): self
    {
        return $this->changed(new self($this->origin, $role, $this->database, $this->setting));
    }

    /**
     * Replaces or removes the database qualifier.
     */
    public function withDatabase(?string $database): self
    {
        return $this->changed(new self($this->origin, $this->role, $database, $this->setting));
    }

    /**
     * Replaces the reset parameter.
     */
    public function withSetting(ResetSetting $setting): self
    {
        return $this->changed(new self($this->origin, $this->role, $this->database, $setting));
    }
}
