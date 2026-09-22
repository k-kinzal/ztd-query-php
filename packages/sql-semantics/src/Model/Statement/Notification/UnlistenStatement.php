<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Notification;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes the subscription to one notification channel.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UNLISTEN events');
 *     $statement instanceof \SqlSemantics\Model\Statement\Notification\UnlistenStatement // => true
 * @visibility public
 */
final class UnlistenStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $channel)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('UnlistenStatement requires PostgreSql.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Unlisten;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->channel);
    }
}
