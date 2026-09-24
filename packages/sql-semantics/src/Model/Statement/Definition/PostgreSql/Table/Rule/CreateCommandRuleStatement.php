<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule;

use Override;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Definition\Relation\Rule\RuleInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Notification\NotifyStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a rewrite rule that runs commands in addition to, or instead of, the matching command.
 * @visibility public
 * @example Reading the actions of a rule
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE log(a INT)')))->bind('CREATE RULE audit AS ON UPDATE TO t DO ALSO (INSERT INTO log VALUES (OLD.a); NOTIFY changed)');
 *     count($statement->actions) // => 2
 *     $statement->actions[1] instanceof \SqlSemantics\Model\Statement\Notification\NotifyStatement // => true
 *     $statement->instead // => false
 */
final class CreateCommandRuleStatement extends BoundStatement
{
    /**
     * @var non-empty-list<BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement> Validated actions in written order
     */
    public readonly array $actions;

    /**
     * @param list<BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement> $actions
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly TableReference $table,
        public readonly RuleEvent $event,
        array $actions,
        public readonly bool $instead = false,
        public readonly ?Expression $condition = null,
        public readonly bool $orReplace = false,
    ) {
        RuleInvariant::identity($origin, $name, $condition);
        $this->actions = RuleInvariant::actions($name, $event, $actions, $instead, $condition, $orReplace);
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
        return new self($origin, $this->name, $this->table, $this->event, $this->actions, $this->instead, $this->condition, $this->orReplace);
    }

    /**
     * Replaces the rule name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->event, $this->actions, $this->instead, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the rewritten command, rebinding the actions against its row images.
     */
    public function withEvent(RuleEvent $event): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $event, $this->actions, $this->instead, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the actions.
     * @param list<BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement> $actions
     */
    public function withActions(array $actions): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $actions, $this->instead, $this->condition, $this->orReplace));
    }

    /**
     * Chooses whether the actions replace the matching command.
     */
    public function withInstead(bool $instead): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $this->actions, $instead, $this->condition, $this->orReplace));
    }

    /**
     * Replaces or removes the condition.
     */
    public function withCondition(?Expression $condition): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $this->actions, $this->instead, $condition, $this->orReplace));
    }

    /**
     * Chooses whether an existing rule of the same name is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $this->actions, $this->instead, $this->condition, $orReplace));
    }
}
