<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Cursor\Handler;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Cursor\Handler\HandlerScan;
use SqlSemantics\Model\Cursor\Handler\HandlerTarget;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads rows through an open handler in natural table order, optionally filtered and limited.
 * @visibility public
 * @example Scanning from the first row
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('HANDLER t READ FIRST WHERE id > 1 LIMIT 5');
 *     [$statement->scan->value, $statement->limit->count->spelling()] // => ['FIRST', '5']
 */
final class ReadHandlerStatement extends BoundStatement
{
    /**
     * @param TableReference $handler Handler name resolved as a table
     * @param Expression|null $where Row condition; null returns every row read
     * @param RowWindow|null $limit Rows returned; null returns one row
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $handler, public readonly HandlerScan $scan, public readonly ?Expression $where = null, public readonly ?RowWindow $limit = null)
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
        return new self($origin, $this->handler, $this->scan, $this->where, $this->limit);
    }

    /**
     * Restarts or continues the scan.
     */
    public function withScan(HandlerScan $scan): self
    {
        return $this->changed(new self($this->origin, $this->handler, $scan, $this->where, $this->limit));
    }

    /**
     * Replaces or removes the row condition.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->scan, $where, $this->limit));
    }

    /**
     * Replaces or removes the row window.
     */
    public function withLimit(?RowWindow $limit): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->scan, $this->where, $limit));
    }
}
