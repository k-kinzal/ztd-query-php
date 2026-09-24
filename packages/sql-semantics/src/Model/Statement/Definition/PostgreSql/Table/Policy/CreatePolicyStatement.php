<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyInvariant;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyMode;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Defines a row security policy of a table: which rows the listed roles can see and which new rows they can write.
 * @visibility public
 * @example Reading a policy with its defaults made explicit
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE docs(owner TEXT)')))->bind('CREATE POLICY own ON docs USING (owner = CURRENT_USER)');
 *     $statement->roles // => [\SqlSemantics\Model\Configuration\Role\PublicRole::Public]
 *     $statement->check // => null
 *     $statement->toString() // => 'CREATE POLICY "own" ON "public"."docs" AS PERMISSIVE FOR ALL TO PUBLIC USING(("owner" = CURRENT_USER))'
 */
final class CreatePolicyStatement extends BoundStatement
{
    /**
     * @var non-empty-list<NamedRole|SessionRole|PublicRole> Validated roles in written order
     */
    public readonly array $roles;

    /**
     * @param list<NamedRole|SessionRole|PublicRole> $roles
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly TableReference $table,
        array $roles = [PublicRole::Public],
        public readonly PolicyMode $mode = PolicyMode::Permissive,
        public readonly PolicyCommand $command = PolicyCommand::All,
        public readonly ?Expression $using = null,
        public readonly ?Expression $check = null,
    ) {
        PolicyInvariant::identity($origin, $name, $using, $check);
        PolicyInvariant::expressions($command, $using, $check);
        $this->roles = PolicyInvariant::roles($roles);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->table, $this->roles, $this->mode, $this->command, $this->using, $this->check);
    }

    /**
     * Replaces the policy name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->roles, $this->mode, $this->command, $this->using, $this->check));
    }

    /**
     * Replaces the roles the policy applies to.
     * @param list<NamedRole|SessionRole|PublicRole> $roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $roles, $this->mode, $this->command, $this->using, $this->check));
    }

    /**
     * Replaces how the policy combines with others.
     */
    public function withMode(PolicyMode $mode): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->roles, $mode, $this->command, $this->using, $this->check));
    }

    /**
     * Replaces the command the policy applies to, which must accept the current expressions.
     */
    public function withCommand(PolicyCommand $command): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->roles, $this->mode, $command, $this->using, $this->check));
    }

    /**
     * Replaces or removes the visibility expression.
     */
    public function withUsing(?Expression $using): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->roles, $this->mode, $this->command, $using, $this->check));
    }

    /**
     * Replaces or removes the written-row expression.
     */
    public function withCheck(?Expression $check): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->roles, $this->mode, $this->command, $this->using, $check));
    }
}
