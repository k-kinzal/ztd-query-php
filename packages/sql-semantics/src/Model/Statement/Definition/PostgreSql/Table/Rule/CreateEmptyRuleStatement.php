<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Definition\Relation\Rule\RuleInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a rewrite rule that does nothing, so with INSTEAD it suppresses the matching command.
 * @visibility public
 * @example Reading a suppressing rule
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE keep AS ON DELETE TO t WHERE OLD.a > 0 DO INSTEAD NOTHING');
 *     $statement->instead // => true
 *     $statement->toString() // => 'CREATE RULE "keep" AS ON DELETE TO "public"."t" WHERE ("old"."a" > 0) DO INSTEAD NOTHING'
 */
final class CreateEmptyRuleStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly TableReference $table,
        public readonly RuleEvent $event,
        public readonly bool $instead = false,
        public readonly ?Expression $condition = null,
        public readonly bool $orReplace = false,
    ) {
        RuleInvariant::identity($origin, $name, $condition);
        if ($event === RuleEvent::Select) {
            throw new InvalidStructure('A rule on SELECT must do INSTEAD one query.');
        }
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
        return new self($origin, $this->name, $this->table, $this->event, $this->instead, $this->condition, $this->orReplace);
    }

    /**
     * Replaces the rule name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->event, $this->instead, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the rewritten command, rebinding the condition against its row images.
     */
    public function withEvent(RuleEvent $event): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $event, $this->instead, $this->condition, $this->orReplace));
    }

    /**
     * Chooses whether the matching command is suppressed or still runs.
     */
    public function withInstead(bool $instead): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $instead, $this->condition, $this->orReplace));
    }

    /**
     * Replaces or removes the condition.
     */
    public function withCondition(?Expression $condition): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $this->instead, $condition, $this->orReplace));
    }

    /**
     * Chooses whether an existing rule of the same name is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->event, $this->instead, $this->condition, $orReplace));
    }
}
