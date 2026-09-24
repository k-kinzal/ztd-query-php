<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the roles or expressions of a row security policy; an absent operand keeps its current value.
 * @visibility public
 * @example Reading a policy alteration
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE docs(owner TEXT)')))->bind('ALTER POLICY own ON docs TO staff, CURRENT_ROLE');
 *     $statement->roles[0]->name ?? null // => 'staff'
 *     $statement->roles[1] ?? null // => \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentRole
 *     $statement->using // => null
 */
final class AlterPolicyStatement extends BoundStatement
{
    /**
     * @var non-empty-list<NamedRole|SessionRole|PublicRole>|null Validated replacement roles; null keeps the current roles
     */
    public readonly ?array $roles;

    /**
     * @param list<NamedRole|SessionRole|PublicRole>|null $roles
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly TableReference $table,
        ?array $roles = null,
        public readonly ?Expression $using = null,
        public readonly ?Expression $check = null,
    ) {
        PolicyInvariant::identity($origin, $name, $using, $check);
        $this->roles = $roles === null ? null : PolicyInvariant::roles($roles);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->table, $this->roles, $this->using, $this->check);
    }

    /**
     * Replaces the altered policy's name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->roles, $this->using, $this->check));
    }

    /**
     * Replaces the new roles, or keeps the current roles with null.
     * @param list<NamedRole|SessionRole|PublicRole>|null $roles
     */
    public function withRoles(?array $roles): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $roles, $this->using, $this->check));
    }

    /**
     * Replaces the new visibility expression, or keeps the current one with null.
     */
    public function withUsing(?Expression $using): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->roles, $using, $this->check));
    }

    /**
     * Replaces the new written-row expression, or keeps the current one with null.
     */
    public function withCheck(?Expression $check): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->roles, $this->using, $check));
    }
}
