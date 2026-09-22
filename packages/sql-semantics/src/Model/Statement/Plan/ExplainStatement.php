<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Plan;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Plan\PlanOptions;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reports the plan of one structurally bound statement without executing it here.
 * @visibility public
 */
final class ExplainStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly BoundStatement $statement, public readonly PlanOptions $options)
    {
        parent::__construct($origin);
        if ($origin->dialect !== $statement->origin->dialect || $origin->dialect !== $options->dialect()) {
            throw new InvalidStructure('An EXPLAIN statement and its operands must use one dialect.');
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Explain;
    }

    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->statement, $this->options);
    }

    public function withStatement(BoundStatement $statement): self
    {
        return $this->changed(new self($this->origin, $statement, $this->options));
    }

    public function withOptions(PlanOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->statement, $options));
    }
}
