<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Deletes the binary log files last modified before a point in time; PURGE MASTER LOGS is the same request.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("PURGE BINARY LOGS BEFORE '2024-01-01 00:00:00'");
 *     $statement->moment->spelling() // => "'2024-01-01 00:00:00'"
 */
final class PurgeBinaryLogsBeforeStatement extends BoundStatement
{
    /**
     * Records the cut-off expression without evaluating it.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Expression $moment)
    {
        ReplicationRelease::require($origin, 'PURGE BINARY LOGS');
        if ($moment->type->dialect !== $origin->dialect) {
            throw new InvalidStructure('The purge cut-off must use the statement dialect.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Purge;
    }

    /**
     * Retains the cut-off when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->moment);
    }

    /**
     * Replaces the cut-off expression.
     */
    public function withMoment(Expression $moment): self
    {
        return $this->changed(new self($this->origin, $moment));
    }
}
