<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Policy;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
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
 * A request to create a row security policy.
 *
 * Mirrors PostgreSQL's `CreatePolicyStmt` (`policy_name`, `table`, `cmd_name`, `permissive`, `roles`, `qual`,
 * `with_check`). Rule PG-POLICY-001: the table is resolved and is the relation fact of the statement; the
 * USING and WITH CHECK expressions are conditions where the table is visible. AS takes PERMISSIVE or
 * RESTRICTIVE; the grammar rejects another word.
 * Source: https://www.postgresql.org/docs/17/sql-createpolicy.html.
 *
 * @visibility SqlSemantics
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
        Check::input($mode === null || in_array($mode->value, ['permissive', 'restrictive'], true), 'A policy is permissive or restrictive.');
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
        $out->keyword('CREATE', 'POLICY')->name($this->name)->keyword('ON');
        (new Spelling())->qualified($out, $this->table);
        if ($this->mode !== null) {
            $out->keyword('AS')->name($this->mode, NameUse::Identifier);
        }
        if ($this->command !== null) {
            $out->keyword('FOR', $this->command->value);
        }
        (new Policies())->write($out, $this->roles, $this->using, $this->check);
    }
}
