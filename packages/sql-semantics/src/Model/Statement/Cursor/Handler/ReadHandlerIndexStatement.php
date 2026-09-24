<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Cursor\Handler;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Cursor\Handler\HandlerTarget;
use SqlSemantics\Model\Cursor\Handler\IndexStep;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads rows through an open handler by walking a named index, optionally filtered and limited.
 * @visibility public
 * @example Walking an index backwards
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('HANDLER t READ k PREV');
 *     [$statement->index, $statement->step->value] // => ['k', 'PREV']
 */
final class ReadHandlerIndexStatement extends BoundStatement
{
    /**
     * @param TableReference $handler Handler name resolved as a table
     * @param string $index Walked index name
     * @param Expression|null $where Row condition; null returns every row read
     * @param RowWindow|null $limit Rows returned; null returns one row
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $handler, public readonly string $index, public readonly IndexStep $step, public readonly ?Expression $where = null, public readonly ?RowWindow $limit = null)
    {
        HandlerTarget::validate($origin, $handler, $where);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Handler;
    }

    /**
     * Retains the read request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->handler, $this->index, $this->step, $this->where, $this->limit);
    }

    /**
     * Walks another index.
     */
    public function withIndex(string $index): self
    {
        return $this->changed(new self($this->origin, $this->handler, $index, $this->step, $this->where, $this->limit));
    }

    /**
     * Moves in another direction along the index.
     */
    public function withStep(IndexStep $step): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $step, $this->where, $this->limit));
    }

    /**
     * Replaces or removes the row condition.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $this->step, $where, $this->limit));
    }

    /**
     * Replaces or removes the row window.
     */
    public function withLimit(?RowWindow $limit): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $this->step, $this->where, $limit));
    }
}
