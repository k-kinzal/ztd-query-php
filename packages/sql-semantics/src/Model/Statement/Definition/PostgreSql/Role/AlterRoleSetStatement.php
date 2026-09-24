<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Stores a session default for one role or every role, optionally within one database.
 * @visibility public
 * @example Reading the stored default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
 *     $statement->role // => \SqlSemantics\Model\Definition\Role\AllRoles::All
 *     $statement->database // => 'app'
 *     $statement->setting->name // => ['search_path']
 * @example Rejecting an empty database qualifier
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER ROLE app SET work_mem = 1');
 *     $statement->withDatabase(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterRoleSetStatement extends BoundStatement
{
    /**
     * The setting uses the session scope because the stored value applies when sessions start.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole|SessionRole|AllRoles $role,
        public readonly ?string $database,
        public readonly AssignedSetting|DefaultSetting|CurrentSetting $setting,
    ) {
        RoleInvariant::database($origin, $database);
        if ($setting->scope !== SettingScope::Session) {
            throw new InvalidStructure('A stored role setting uses the session scope.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the stored default while replacing diagnostic provenance.
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
     * Replaces the stored assignment.
     */
    public function withSetting(AssignedSetting|DefaultSetting|CurrentSetting $setting): self
    {
        return $this->changed(new self($this->origin, $this->role, $this->database, $setting));
    }
}
