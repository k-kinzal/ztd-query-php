<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Cursor\Handler;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Cursor\Handler\HandlerTarget;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Closes an open handler; its name is resolved as a table name and stays diagnosed when it is only an alias.
 * @visibility public
 * @example Closing a handler
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('HANDLER t CLOSE');
 *     $statement->handler->declaration->name // => 't'
 */
final class CloseHandlerStatement extends BoundStatement
{
    /**
     * @param TableReference $handler Handler name resolved as a table
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $handler)
    {
        HandlerTarget::validate($origin, $handler);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Handler;
    }

    /**
     * Retains the handler while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->handler);
    }

    /**
     * Closes another handler.
     */
    public function withHandler(TableReference $handler): self
    {
        return $this->changed(new self($this->origin, $handler));
    }
}
