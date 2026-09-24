<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Deletes the binary log files listed in the index before the named log file; PURGE MASTER LOGS is the same request.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'binlog.000042'");
 *     $statement->logName->text // => "'binlog.000042'"
 */
final class PurgeBinaryLogsToStatement extends BoundStatement
{
    /**
     * Records the named log file without reading the binary log index.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Literal $logName)
    {
        ReplicationRelease::require($origin, 'PURGE BINARY LOGS');
        ReplicationText::check($logName, 'A binary log name');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Purge;
    }

    /**
     * Retains the log name when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->logName);
    }

    /**
     * Replaces the first log file that is kept.
     */
    public function withLogName(Literal $logName): self
    {
        return $this->changed(new self($this->origin, $logName));
    }
}
