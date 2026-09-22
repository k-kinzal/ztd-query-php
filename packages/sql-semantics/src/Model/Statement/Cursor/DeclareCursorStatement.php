<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Cursor;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The structured operands of a DECLARE cursor operation.
 * @visibility public
 */
final class DeclareCursorStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly \SqlSemantics\Model\BoundQuery $query,
        public readonly \SqlSemantics\Model\Cursor\Scrollability $scroll = \SqlSemantics\Model\Cursor\Scrollability::Default,
        public readonly \SqlSemantics\Model\Cursor\Sensitivity $sensitivity = \SqlSemantics\Model\Cursor\Sensitivity::Default,
        public readonly bool $binary = false,
        public readonly bool $hold = false,
    ) {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This cursor command requires PostgreSQL.');
        }
        if ($query->origin->dialect !== $origin->dialect) {
            throw new InvalidStructure('A cursor query must use the statement dialect.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Declare;
    }

    /**
     * Retains the cursor request when updating diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->query, $this->scroll, $this->sensitivity, $this->binary, $this->hold);
    }
}
