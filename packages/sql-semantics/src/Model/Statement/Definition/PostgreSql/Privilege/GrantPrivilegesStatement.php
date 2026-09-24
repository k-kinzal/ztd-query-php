<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Grants privileges on objects of one class to roles, optionally with the grant option.
 * @visibility public
 * @example Reading a column grant
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION');
 *     $statement->privileges[0]->columns // => ['id']
 *     $statement->grantees[0] // => \SqlSemantics\Model\Configuration\Role\PublicRole::Public
 *     $statement->grantOption // => true
 * @example Rejecting a privilege outside the object class domain
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     $target = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass::Database, ['app']);
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement($origin, [new \SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege::Insert)], $target, [\SqlSemantics\Model\Configuration\Role\PublicRole::Public]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class GrantPrivilegesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $grantees Recipient roles
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $privileges,
        public readonly TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets $target,
        public readonly array $grantees,
        public readonly bool $grantOption = false,
        public readonly NamedRole|SessionRole|null $grantor = null,
    ) {
        PrivilegeInvariant::target($origin, $target, $privileges);
        PrivilegeInvariant::grantees($grantees, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Grant;
    }

    /**
     * Retains the grant while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->privileges, $this->target, $this->grantees, $this->grantOption, $this->grantor);
    }

    /**
     * Replaces the complete privilege request.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Replacement privileges
     */
    public function withPrivileges(array $privileges): self
    {
        return $this->changed(new self($this->origin, $privileges, $this->target, $this->grantees, $this->grantOption, $this->grantor));
    }

    /**
     * Replaces the object selection.
     */
    public function withTarget(TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets $target): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $target, $this->grantees, $this->grantOption, $this->grantor));
    }

    /**
     * Replaces the recipients.
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $grantees Replacement recipients
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $grantees, $this->grantOption, $this->grantor));
    }

    /**
     * Replaces whether recipients may grant the privileges onward.
     */
    public function withGrantOption(bool $grantOption): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $grantOption, $this->grantor));
    }

    /**
     * Replaces or removes the role recorded as grantor.
     */
    public function withGrantor(NamedRole|SessionRole|null $grantor): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->grantOption, $grantor));
    }
}
