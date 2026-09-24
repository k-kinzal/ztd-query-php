<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\TlsChannel;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reconfigures a TLS context from the current TLS system variables (ALTER INSTANCE RELOAD TLS).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_admin NO ROLLBACK ON ERROR');
 *     [$statement->channel->value, $statement->rollbackOnError] // => ['mysql_admin', false]
 */
final class ReloadTlsStatement extends BoundStatement
{
    /**
     * An omitted channel is mysql_main; by default a failed reload keeps the previous context.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TlsChannel $channel = TlsChannel::Main, public readonly bool $rollbackOnError = true)
    {
        ReplicationRelease::require($origin, 'ALTER INSTANCE RELOAD TLS', 80000);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the channel and error policy when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->channel, $this->rollbackOnError);
    }

    /**
     * Selects the TLS context to reload.
     */
    public function withChannel(TlsChannel $channel): self
    {
        return $this->changed(new self($this->origin, $channel, $this->rollbackOnError));
    }

    /**
     * Chooses whether a failed reload keeps the previous context.
     */
    public function withRollbackOnError(bool $rollbackOnError): self
    {
        return $this->changed(new self($this->origin, $this->channel, $rollbackOnError));
    }
}
