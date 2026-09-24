<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Condition\SignalAssignment;
use SqlSemantics\Model\Configuration\Condition\SignalAssignments;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Passes on the condition being handled, optionally as another SQLSTATE and with changed condition items.
 * @visibility public
 * @example Reading a resignal without a new condition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("RESIGNAL SET MESSAGE_TEXT = 'again'");
 *     [$statement->condition, count($statement->assignments)] // => [null, 1]
 */
final class ResignalStatement extends BoundStatement
{
    /**
     * @param SqlState|null $condition Replacement SQLSTATE; null keeps the handled condition
     * @param list<SignalAssignment> $assignments Condition items in request order, each at most once
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?SqlState $condition = null, public readonly array $assignments = [])
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('RESIGNAL requires MySQL.');
        }
        SignalAssignments::validate($assignments);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Resignal;
    }

    /**
     * Retains the condition and its items while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->condition, $this->assignments);
    }

    /**
     * Replaces or removes the replacement SQLSTATE.
     */
    public function withCondition(?SqlState $condition): self
    {
        return $this->changed(new self($this->origin, $condition, $this->assignments));
    }

    /**
     * Replaces the condition item values.
     * @param list<SignalAssignment> $assignments
     */
    public function withAssignments(array $assignments): self
    {
        return $this->changed(new self($this->origin, $this->condition, $assignments));
    }
}
