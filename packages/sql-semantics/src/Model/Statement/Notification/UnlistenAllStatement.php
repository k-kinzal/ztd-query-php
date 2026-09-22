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
 * Removes all notification subscriptions.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UNLISTEN *');
 *     $statement instanceof \SqlSemantics\Model\Statement\Notification\UnlistenAllStatement // => true
 * @visibility public
 */
final class UnlistenAllStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('UnlistenAllStatement requires PostgreSql.');
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
        return new static($origin);
    }
}
