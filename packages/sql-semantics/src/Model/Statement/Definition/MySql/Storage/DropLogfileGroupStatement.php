<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Storage;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\RemovalInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests DROP LOGFILE GROUP without deleting any physical storage.
 * @visibility public
 * @example Inspecting the storage identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP LOGFILE GROUP store');
 *     $statement->name // => 'store'
 */
final class DropLogfileGroupStatement extends BoundStatement
{
    /**
     * Records a named storage object and the optional engine selector.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly ?string $engine = null, public readonly CompletionWait $waiting = CompletionWait::Wait)
    {
        RemovalInvariant::target($origin, $name, $engine);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the deletion operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->engine, $this->waiting);
    }

    /**
     * Replaces the target storage identity.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->engine, $this->waiting));
    }

    /**
     * Replaces or removes the explicit engine selector.
     */
    public function withEngine(?string $engine): self
    {
        return $this->changed(new self($this->origin, $this->name, $engine, $this->waiting));
    }

    /**
     * Replaces the completion-wait request without executing it.
     */
    public function withWaiting(CompletionWait $waiting): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->engine, $waiting));
    }
}
