<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Notification;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes a channel notification and its optional literal payload.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("NOTIFY events, 'changed'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Notification\NotifyStatement // => true
 * @visibility public
 */
final class NotifyStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $channel, public readonly ?Literal $payload = null)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('NotifyStatement requires PostgreSql.');
        }
        if ($payload !== null && ($payload->type->dialect !== $origin->dialect || $payload->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('payload must retain the statement dialect and be a text literal.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Notify;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->channel, $this->payload);
    }
}
