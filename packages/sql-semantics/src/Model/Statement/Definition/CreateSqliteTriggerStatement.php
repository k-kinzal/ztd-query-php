<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Trigger;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A SQLite row trigger with a typed event, subject table and executable structure.
 * @visibility public
 */
final class CreateSqliteTriggerStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly TableReference $subject,
        public readonly Trigger\Timing $timing,
        public readonly Trigger\Event $event,
        public readonly Trigger\SqliteBody $body,
        public readonly ?Expression $when = null,
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
    ) {
        parent::__construct($origin);
        if ($origin->dialect !== Dialect::Sqlite || $when !== null && $when->type->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('A SQLite trigger and its condition must use the SQLite dialect.');
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->subject, $this->timing, $this->event, $this->body, $this->when, $this->temporary, $this->ifNotExists);
    }

    /**
     * Changes the trigger predicate and rebinds its row-image references.
     */
    public function withWhen(?Expression $when): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->subject, $this->timing, $this->event, $this->body, $when, $this->temporary, $this->ifNotExists));
    }

    /**
     * Changes the ordered trigger program after checking each allowed action.
     */
    public function withBody(Trigger\SqliteBody $body): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->subject, $this->timing, $this->event, $body, $this->when, $this->temporary, $this->ifNotExists));
    }
}
