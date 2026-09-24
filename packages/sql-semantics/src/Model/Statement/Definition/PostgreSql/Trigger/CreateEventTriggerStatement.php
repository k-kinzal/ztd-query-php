<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Trigger\DdlCommandTag;
use SqlSemantics\Model\Definition\Trigger\EventTriggerEvent;
use SqlSemantics\Model\Definition\Trigger\EventTriggerInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a database-wide trigger that runs a function on a DDL, rewrite, or login event, optionally only for some command tags.
 * @visibility public
 * @example Reading an event trigger with a tag filter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE EVENT TRIGGER guard ON table_rewrite WHEN tag IN ('alter table') EXECUTE PROCEDURE stop_rewrite()");
 *     $statement->event // => \SqlSemantics\Model\Definition\Trigger\EventTriggerEvent::TableRewrite
 *     $statement->toString() // => 'CREATE EVENT TRIGGER "guard" ON "table_rewrite" WHEN TAG IN(\'ALTER TABLE\') EXECUTE FUNCTION "stop_rewrite"()'
 */
final class CreateEventTriggerStatement extends BoundStatement
{
    /**
     * @param list<DdlCommandTag> $tags Command tags filtering the event; empty when every command fires
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly EventTriggerEvent $event,
        public readonly QualifiedName $function,
        public readonly array $tags = [],
    ) {
        EventTriggerInvariant::target($origin, $name);
        Collections::objects($tags, DdlCommandTag::class);
        if ($event === EventTriggerEvent::Login && $tags !== []) {
            throw new InvalidStructure('A login event trigger cannot filter by tag.');
        }
        if ($event === EventTriggerEvent::TableRewrite && array_diff(array_map(static fn (DdlCommandTag $tag): string => $tag->value, $tags), ['ALTER TABLE', 'ALTER TYPE', 'ALTER MATERIALIZED VIEW']) !== []) {
            throw new InvalidStructure('A table_rewrite event trigger filters only ALTER TABLE, ALTER TYPE and ALTER MATERIALIZED VIEW.');
        }
        if (count($function->parts) > 3) {
            throw new InvalidStructure('A trigger function name has at most three parts.');
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
        return new self($origin, $this->name, $this->event, $this->function, $this->tags);
    }

    /**
     * Replaces the event trigger name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->event, $this->function, $this->tags));
    }

    /**
     * Replaces the firing event, which must accept the current tag filter.
     */
    public function withEvent(EventTriggerEvent $event): self
    {
        return $this->changed(new self($this->origin, $this->name, $event, $this->function, $this->tags));
    }

    /**
     * Replaces the executed function.
     */
    public function withFunction(QualifiedName $function): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->event, $function, $this->tags));
    }

    /**
     * Replaces or removes the tag filter.
     * @param list<DdlCommandTag> $tags
     */
    public function withTags(array $tags): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->event, $this->function, $tags));
    }
}
