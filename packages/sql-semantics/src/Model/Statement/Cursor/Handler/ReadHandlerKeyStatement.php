<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Cursor\Handler;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Cursor\Handler\HandlerTarget;
use SqlSemantics\Model\Cursor\Handler\KeyComparison;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads rows through an open handler by positioning a named index on a key, optionally filtered and limited.
 * @visibility public
 * @example Looking up a key prefix
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, KEY k(a, b))');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('HANDLER t READ k >= (1, 2)');
 *     [$statement->comparison->value, count($statement->key)] // => ['>=', 2]
 */
final class ReadHandlerKeyStatement extends BoundStatement
{
    /**
     * @var non-empty-list<Expression>
     */
    public readonly array $key;

    /**
     * @param TableReference $handler Handler name resolved as a table
     * @param string $index Searched index name
     * @param list<Expression> $key Values for the leading index columns
     * @param Expression|null $where Row condition; null returns every row read
     * @param RowWindow|null $limit Rows returned; null returns one row
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $handler, public readonly string $index, public readonly KeyComparison $comparison, array $key, public readonly ?Expression $where = null, public readonly ?RowWindow $limit = null)
    {
        HandlerTarget::validate($origin, $handler, $where);
        Collections::objects($key, Expression::class);
        $this->key = Collections::nonEmpty($key);
        foreach ($this->key as $value) {
            if ($value->type->dialect !== Dialect::MySql) {
                throw new InvalidStructure('HANDLER key values must use MySQL.');
            }
        }
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
        return new self($origin, $this->handler, $this->index, $this->comparison, $this->key, $this->where, $this->limit);
    }

    /**
     * Searches another index.
     */
    public function withIndex(string $index): self
    {
        return $this->changed(new self($this->origin, $this->handler, $index, $this->comparison, $this->key, $this->where, $this->limit));
    }

    /**
     * Positions on the key with another comparison.
     */
    public function withComparison(KeyComparison $comparison): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $comparison, $this->key, $this->where, $this->limit));
    }

    /**
     * Searches another nonempty key.
     * @param list<Expression> $key
     */
    public function withKey(array $key): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $this->comparison, $key, $this->where, $this->limit));
    }

    /**
     * Replaces or removes the row condition.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $this->comparison, $this->key, $where, $this->limit));
    }

    /**
     * Replaces or removes the row window.
     */
    public function withLimit(?RowWindow $limit): self
    {
        return $this->changed(new self($this->origin, $this->handler, $this->index, $this->comparison, $this->key, $this->where, $limit));
    }
}
