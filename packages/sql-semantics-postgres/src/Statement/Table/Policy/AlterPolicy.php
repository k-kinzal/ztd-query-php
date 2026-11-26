<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Policy;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Policies;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;

/**
 * A request to change the roles or conditions of a row security policy.
 *
 * Mirrors PostgreSQL's `AlterPolicyStmt`. The table is resolved and is the relation fact of the statement;
 * the conditions are derived as in CREATE POLICY (PG-POLICY-001).
 * Source: https://www.postgresql.org/docs/17/sql-alterpolicy.html.
 *
 * @visibility SqlSemantics
 */
final class AlterPolicy implements Statement, Relation
{
    use Snapshot;

    /**
     * @var list<RoleSpec> The new roles; none keeps them
     */
    public readonly array $roles;

    /**
     * @param Name $name The policy name
     * @param QualifiedName $table The table
     * @param list<RoleSpec> $roles The new roles; none keeps them
     * @param Scalar|null $using The new USING condition
     * @param Scalar|null $check The new WITH CHECK condition
     */
    public function __construct(
        public readonly Name $name,
        public readonly QualifiedName $table,
        array $roles = [],
        public readonly ?Scalar $using = null,
        public readonly ?Scalar $check = null,
    ) {
        $this->roles = Check::listOf($roles, RoleSpec::class, 'Policy roles are roles.');
    }

    /**
     * Resolves the table and derives the conditions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Policies())->derive($this, $this->table, $this->using, $this->check, $derivation);
    }

    /**
     * Resolves the table and answers its facts.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->resolve($derivation, $this->table);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'POLICY')->name($this->name)->keyword('ON');
        (new Spelling())->qualified($out, $this->table);
        (new Policies())->write($out, $this->roles, $this->using, $this->check);
    }
}
