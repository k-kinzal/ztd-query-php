<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\RewriteRules;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
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
 * A request to create a rewrite rule: commands that replace or accompany a command on a table or view.
 *
 * Mirrors PostgreSQL's `RuleStmt` (`relation`, `rulename`, `whereClause`, `event`, `instead`, `actions`,
 * `replace`). Rule PG-REWRITE-RULE-001: the table is resolved and is the relation fact of the statement; the
 * WHERE condition sees OLD and NEW (PG-OLD-NEW-001); the actions see OLD and NEW with a qualifier only.
 * NOTHING is no action without parentheses; empty statements between semicolons are not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createrule.html.
 *
 * @visibility public
 * @example Reading a rule
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OR REPLACE RULE r AS ON UPDATE TO t WHERE old.a <> new.a DO ALSO (INSERT INTO log VALUES (old.a); DELETE FROM log WHERE a = new.a)');
 *     [count($statement->statement->actions), $statement->toString()] // => [2, 'CREATE OR REPLACE RULE r AS ON UPDATE TO t WHERE old.a <> new.a DO ALSO (INSERT INTO log VALUES (old.a); DELETE FROM log WHERE a = new.a)']
 */
final class CreateRule implements Statement, Relation
{
    use Snapshot;

    /**
     * @var list<Statement> The actions in order; none is NOTHING or an empty list
     */
    public readonly array $actions;

    /**
     * @param Name $name The rule name
     * @param RuleEvent $event The rewritten command
     * @param QualifiedName $table The table or view
     * @param list<Statement> $actions The actions in order; none is NOTHING or an empty list
     * @param bool $grouped Whether the actions are written between parentheses
     * @param bool|null $instead True for INSTEAD, false for ALSO, null when not written
     * @param Scalar|null $where The condition
     * @param bool $replace Whether OR REPLACE is written
     */
    public function __construct(
        public readonly Name $name,
        public readonly RuleEvent $event,
        public readonly QualifiedName $table,
        array $actions = [],
        public readonly bool $grouped = false,
        public readonly ?bool $instead = null,
        public readonly ?Scalar $where = null,
        public readonly bool $replace = false,
    ) {
        $this->actions = Check::listOf($actions, Statement::class, 'Rule actions are statements.');
        Check::input($grouped || count($this->actions) <= 1, 'Several actions are written between parentheses.');
    }

    /**
     * Resolves the table and derives the condition and the actions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RewriteRules())->derive($this, $derivation);
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
        (new RewriteRules())->write($out, $this);
    }
}
