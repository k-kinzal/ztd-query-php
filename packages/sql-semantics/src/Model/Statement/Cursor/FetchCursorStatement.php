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
 * The structured operands of a FETCH cursor operation.
 * @visibility public
  * @example Inspecting FetchCursorStatement
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('FETCH BACKWARD ALL FROM cur');
 *     $statement instanceof \SqlSemantics\Model\Statement\Cursor\FetchCursorStatement // => true
 */
final class FetchCursorStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly \SqlSemantics\Model\Cursor\Movement $movement,
    ) {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This cursor command requires PostgreSQL.');
        }

        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Fetch;
    }

    /**
     * Retains the cursor request when updating diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->movement);
    }
}
