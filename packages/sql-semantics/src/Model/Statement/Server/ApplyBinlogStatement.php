<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes a binary log event supplied as a base64 string literal.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("BINLOG 'YWJj'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\ApplyBinlogStatement // => true
 * @visibility public
 */
final class ApplyBinlogStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Literal $encodedEvent)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('ApplyBinlogStatement requires MySql.');
        }
        if (($encodedEvent->type->dialect !== $origin->dialect || $encodedEvent->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('encodedEvent must retain the statement dialect and be a text literal.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Binlog;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->encodedEvent);
    }
}
