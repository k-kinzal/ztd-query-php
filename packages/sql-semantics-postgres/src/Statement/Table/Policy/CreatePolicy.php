<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Policy;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Policies;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a row security policy.
 *
 * Mirrors PostgreSQL's `CreatePolicyStmt` (`policy_name`, `table`, `cmd_name`, `permissive`, `roles`, `qual`,
 * `with_check`). Rule PG-POLICY-001: the table is resolved and is the relation fact of the statement; the
 * USING and WITH CHECK expressions are conditions where the table is visible. AS takes PERMISSIVE or
 * RESTRICTIVE; another word is reported.
 * Source: https://www.postgresql.org/docs/17/sql-createpolicy.html.
 *
 * @visibility public
 * @example Reading a policy
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE POLICY p ON t AS RESTRICTIVE FOR UPDATE TO CURRENT_USER USING (a > 0) WITH CHECK (a < 10)');
 *     $statement->toString() // => 'CREATE POLICY p ON t AS restrictive FOR UPDATE TO CURRENT_USER USING (a > 0) WITH CHECK (a < 10)'
 */
final class CreatePolicy implements Statement, Relation
{
    use Snapshot;

    /**
     * @var list<RoleSpec> The roles after TO; none means PUBLIC
     */
    public readonly array $roles;

    /**
     * @param Name $name The policy name
     * @param QualifiedName $table The table
     * @param Name|null $mode The word after AS: permissive or restrictive
     * @param PolicyCommand|null $command The command after FOR
     * @param list<RoleSpec> $roles The roles after TO; none means PUBLIC
     * @param Scalar|null $using The USING condition
     * @param Scalar|null $check The WITH CHECK condition
     */
    public function __construct(
        public readonly Name $name,
        public readonly QualifiedName $table,
        public readonly ?Name $mode = null,
        public readonly ?PolicyCommand $command = null,
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
        (new Policies())->mode($this->mode, $derivation);
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
        $out->keyword('CREATE', 'POLICY')->name($this->name)->keyword('ON');
        (new Spelling())->qualified($out, $this->table);
        if ($this->mode !== null) {
            $out->keyword('AS')->name($this->mode, NameUse::Label);
        }
        if ($this->command !== null) {
            $out->keyword('FOR', $this->command->value);
        }
        (new Policies())->write($out, $this->roles, $this->using, $this->check);
    }
}
