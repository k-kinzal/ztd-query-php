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
 * Raises a condition with an SQLSTATE and optional condition item values; outside a stored program no named condition is declared.
 * @visibility public
 * @example Reading the signaled condition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE VALUE '45000' SET MYSQL_ERRNO = 1644");
 *     [$statement->condition->code, $statement->assignments[0]->item->value] // => ['45000', 'MYSQL_ERRNO']
 */
final class SignalStatement extends BoundStatement
{
    /**
     * @param list<SignalAssignment> $assignments Condition items in request order, each at most once
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly SqlState $condition, public readonly array $assignments = [])
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('SIGNAL requires MySQL.');
        }
        SignalAssignments::validate($assignments);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Signal;
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
     * Signals another SQLSTATE with the same items.
     */
    public function withCondition(SqlState $condition): self
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
